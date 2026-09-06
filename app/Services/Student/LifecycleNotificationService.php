<?php

namespace App\Services\Student;

use App\Models\Guardian;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Phase 7 lifecycle communications — preferences, recipients, logging.
 *
 * Does not own lifecycle transitions. Notification failure never rolls back domain state.
 */
class LifecycleNotificationService
{
    public function __construct(
        protected SmsService $sms
    ) {}

    /**
     * Whether the school enabled this lifecycle preference for a specific audience.
     *
     * Settings shape: ['admin' => bool, 'parent' => bool, ...].
     * Lifecycle candidate/guardian notifications use audience "parent".
     * Staff-facing notifications use "admin" (or teacher when applicable).
     * When $audience is null, returns true only if the preference is a scalar true
     * or the array is non-empty with at least one true — callers should pass audience.
     */
    public function isEnabled(School $school, string $preferenceKey, ?string $audience = 'parent'): bool
    {
        $settings = getMergedSettings('general.notifications', $school) ?? [];
        $pref = $settings[$preferenceKey] ?? null;

        if ($pref === null) {
            // Unspecified preference keys default to enabled.
            return true;
        }

        if (is_bool($pref)) {
            return $pref;
        }

        if (is_array($pref)) {
            if ($audience !== null) {
                return (bool) ($pref[$audience] ?? false);
            }

            return in_array(true, $pref, true);
        }

        return (bool) $pref;
    }

    /**
     * Match NotificationLog.recipient against route identity and bare address forms.
     */
    protected function applyRecipientFilter($query, ?object $recipient)
    {
        if ($recipient === null) {
            return $query;
        }

        if ($recipient instanceof \Illuminate\Database\Eloquent\Model && $recipient->getKey()) {
            return $query
                ->where('notifiable_type', $recipient->getMorphClass())
                ->where('notifiable_id', $recipient->getKey());
        }

        $identity = $this->recipientIdentity($recipient);
        $email = $this->recipientEmail($recipient);
        $phone = $this->normalizePhone($this->recipientPhone($recipient));

        return $query->where(function ($q) use ($identity, $email, $phone) {
            if ($identity !== '') {
                $q->orWhere('recipient', $identity);
            }
            if ($email !== '') {
                $q->orWhere('recipient', $email)
                    ->orWhere('recipient', 'mail:'.strtolower(trim($email)));
            }
            if ($phone !== '') {
                $q->orWhere('recipient', $phone)
                    ->orWhere('recipient', 'sms:'.$phone);
            }
        });
    }

    public function alreadyDeliveredSuccessfully(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey,
        ?object $recipient = null
    ): bool {
        $q = NotificationLog::query()
            ->where('school_id', $school->id)
            ->where('notification_type', $notificationClass)
            ->where('success', true)
            ->where('metadata->reminder_key', $reminderKey)
            ->where('metadata->lifecycle_type', $context->getMorphClass())
            ->where('metadata->lifecycle_id', (string) $context->getKey());

        $q = $this->applyRecipientFilter($q, $recipient);

        return $q->exists();
    }

    /**
     * True when a non-terminal dispatch is still in flight for this reminder key
     * (queued/accepted but not yet delivered or failed). Prevents duplicate enqueue
     * while a prior copy is pending. Failed dispatches remain retryable.
     */
    public function hasPendingDispatch(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey,
        ?object $recipient = null
    ): bool {
        $q = NotificationLog::query()
            ->where('school_id', $school->id)
            ->where('notification_type', $notificationClass)
            ->where('success', false)
            ->where('metadata->reminder_key', $reminderKey)
            ->where('metadata->lifecycle_type', $context->getMorphClass())
            ->where('metadata->lifecycle_id', (string) $context->getKey())
            ->where(function ($inner) {
                $inner->where('metadata->phase', 'dispatched')
                    ->orWhere('metadata->phase', 'claimed');
            });

        $q = $this->applyRecipientFilter($q, $recipient);

        return $q->exists();
    }

    /**
     * Skip recipient when already delivered or a dispatch is still pending.
     */
    public function shouldSuppressReminder(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey,
        ?object $recipient = null
    ): bool {
        return $this->alreadyDeliveredSuccessfully(
            $school, $context, $notificationClass, $reminderKey, $recipient
        ) || $this->hasPendingDispatch(
            $school, $context, $notificationClass, $reminderKey, $recipient
        );
    }

    /**
     * Dispatch a lifecycle notification to resolved recipients.
     *
     * When audience is omitted, delivers independently to every audience enabled
     * in the preference (parent + admin), matching the seeded settings shape.
     *
     * @return int number of notifiables notified (0 if skipped/disabled/no recipients)
     */
    public function notify(
        School $school,
        string $preferenceKey,
        string $notificationClass,
        Model $context,
        array $extra = []
    ): int {
        $explicitAudience = $extra['audience'] ?? null;
        if ($explicitAudience !== null) {
            return $this->notifyAudience(
                $school,
                $preferenceKey,
                $notificationClass,
                $context,
                $explicitAudience,
                $extra
            );
        }

        $sent = 0;
        foreach (['parent', 'admin'] as $audience) {
            if ($this->isEnabled($school, $preferenceKey, $audience)) {
                $sent += $this->notifyAudience(
                    $school,
                    $preferenceKey,
                    $notificationClass,
                    $context,
                    $audience,
                    $extra
                );
            }
        }

        return $sent;
    }

    /**
     * Deliver to one audience (parent/candidate or admin/staff).
     */
    protected function notifyAudience(
        School $school,
        string $preferenceKey,
        string $notificationClass,
        Model $context,
        string $audience,
        array $extra = []
    ): int {
        if (! $this->isEnabled($school, $preferenceKey, $audience)) {
            return 0;
        }

        $reminderKey = $extra['reminder_key'] ?? null;
        // Scope reminder keys per audience so parent success does not suppress admin.
        $audienceReminderKey = $reminderKey !== null
            ? $reminderKey.':'.$audience
            : null;

        $recipients = $this->resolveRecipients($context, $audience, $school);
        if ($recipients->isEmpty()) {
            Log::info('Lifecycle notification skipped: no recipients', [
                'school_id' => $school->id,
                'context' => get_class($context),
                'context_id' => $context->getKey(),
                'notification' => $notificationClass,
                'preference_key' => $preferenceKey,
                'audience' => $audience,
                'reminder_key' => $reminderKey,
            ]);

            return 0;
        }

        if ($audienceReminderKey) {
            $claimed = [];
            foreach ($recipients as $recipient) {
                if ($this->claimReminderSlot(
                    $school,
                    $context,
                    $notificationClass,
                    $audienceReminderKey,
                    $recipient
                )) {
                    $claimed[] = $recipient;
                }
            }
            $recipients = collect($claimed)->values();
            if ($recipients->isEmpty()) {
                return 0;
            }
        }

        $sent = 0;
        $payload = array_merge($extra, ['audience' => $audience]);
        foreach ($recipients as $recipient) {
            $channels = $this->channelsFor($recipient, $school);
            if ($channels === []) {
                continue;
            }

            foreach ($channels as $channel) {
                if ($channel === 'mail') {
                    $sent += $this->dispatchMail(
                        $school,
                        $context,
                        $recipient,
                        $notificationClass,
                        $preferenceKey,
                        $audienceReminderKey,
                        $payload
                    ) ? 1 : 0;
                } elseif ($channel === 'sms') {
                    $sent += $this->dispatchSms(
                        $school,
                        $context,
                        $recipient,
                        $notificationClass,
                        $preferenceKey,
                        $audienceReminderKey,
                        $payload
                    ) ? 1 : 0;
                }
            }
        }

        return $sent;
    }

    /**
     * Atomically claim a reminder delivery slot for one recipient.
     * Transaction + row lock so concurrent workers cannot both dispatch.
     * Failed prior attempts remain retryable.
     */
    public function claimReminderSlot(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey,
        ?object $recipient = null
    ): bool {
        return \Illuminate\Support\Facades\DB::transaction(function () use (
            $school, $context, $notificationClass, $reminderKey, $recipient
        ) {
            $q = NotificationLog::query()
                ->where('school_id', $school->id)
                ->where('notification_type', $notificationClass)
                ->where('metadata->reminder_key', $reminderKey)
                ->where('metadata->lifecycle_type', $context->getMorphClass())
                ->where('metadata->lifecycle_id', (string) $context->getKey())
                ->where(function ($inner) {
                    $inner->where('success', true)
                        ->orWhere('metadata->phase', 'dispatched')
                        ->orWhere('metadata->phase', 'claimed');
                });
            $q = $this->applyRecipientFilter($q, $recipient);
            if ($q->lockForUpdate()->exists()) {
                return false;
            }

            $this->logDispatch(
                $school,
                $context,
                $recipient ?? new AnonymousNotifiable,
                $notificationClass,
                'claim',
                false,
                null,
                [
                    'reminder_key' => $reminderKey,
                    'phase' => 'claimed',
                    'channel' => 'claim',
                ]
            );

            return true;
        });
    }

    /**
     * Supported channels for a recipient under current school infrastructure.
     *
     * @return list<string>
     */
    public function channelsFor(object $recipient, School $school): array
    {
        $channels = [];
        if ($this->hasValidEmail($recipient)) {
            $channels[] = 'mail';
        }
        if ($this->hasValidPhone($recipient) && $this->smsEnabledForSchool($school)) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    protected function smsEnabledForSchool(School $school): bool
    {
        $smsSettings = getMergedSettings('sms', $school) ?? [];

        return ! empty($smsSettings['enabled'] ?? true)
            && ! empty($smsSettings['providers'] ?? []);
    }

    // NOTE: Remaining methods (dispatchMail, dispatchSms, resolveRecipients, resolveStaffRecipients,
    // resolveForEnrollment/Admission/Application, logging helpers) continue below.
    // This partial push is INVALID - use full file from artifacts.
}
