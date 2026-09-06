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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Phase 7 lifecycle communications — preferences, recipients, logging.
 *
 * Does not own lifecycle transitions. Notification failure never rolls back domain state.
 */
class LifecycleNotificationService
{
    use Concerns\DispatchesLifecycleNotifications;

    public function __construct(
        protected SmsService $sms
    ) {}

    public function isEnabled(School $school, string $preferenceKey, ?string $audience = 'parent'): bool
    {
        $settings = getMergedSettings('general.notifications', $school) ?? [];
        $pref = $settings[$preferenceKey] ?? null;

        if ($pref === null) {
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
     * Match reminder_key exactly or with an audience suffix / stripped audience.
     * processIncompleteReminders stores base keys; notifyAudience logs "{base}:{audience}".
     * markDelivered tests may query either form — both must resolve consistently.
     */
    protected function applyReminderKeyFilter($query, string $reminderKey)
    {
        return $query->where(function ($q) use ($reminderKey) {
            $q->where('metadata->reminder_key', $reminderKey);
            foreach (['parent', 'admin'] as $audience) {
                $q->orWhere('metadata->reminder_key', $reminderKey.':'.$audience);
            }
            if (str_ends_with($reminderKey, ':parent') || str_ends_with($reminderKey, ':admin')) {
                $base = preg_replace('/:(parent|admin)$/', '', $reminderKey);
                if (is_string($base) && $base !== '' && $base !== $reminderKey) {
                    $q->orWhere('metadata->reminder_key', $base);
                }
            }
        });
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
        ?object $recipient = null,
        ?string $channel = null
    ): bool {
        $q = NotificationLog::query()
            ->where('school_id', $school->id)
            ->where('notification_type', $notificationClass)
            ->where('success', true)
            ->where('metadata->lifecycle_type', $context->getMorphClass())
            ->where('metadata->lifecycle_id', (string) $context->getKey());
        $q = $this->applyReminderKeyFilter($q, $reminderKey);

        if ($channel !== null) {
            $q->where(function ($inner) use ($channel) {
                $inner->where('channel', $channel)
                    ->orWhere('metadata->channel', $channel);
            });
        }

        $q = $this->applyRecipientFilter($q, $recipient);

        return $q->exists();
    }

    public function hasPendingDispatch(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey,
        ?object $recipient = null,
        ?string $channel = null
    ): bool {
        $q = NotificationLog::query()
            ->where('school_id', $school->id)
            ->where('notification_type', $notificationClass)
            ->where('success', false)
            ->where('metadata->lifecycle_type', $context->getMorphClass())
            ->where('metadata->lifecycle_id', (string) $context->getKey())
            ->where('metadata->phase', 'dispatched');
        $q = $this->applyReminderKeyFilter($q, $reminderKey);

        if ($channel !== null) {
            $q->where(function ($inner) use ($channel) {
                $inner->where('channel', $channel)
                    ->orWhere('metadata->channel', $channel);
            });
        }

        $q = $this->applyRecipientFilter($q, $recipient);

        return $q->exists();
    }

    public function shouldSuppressReminder(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey,
        ?object $recipient = null,
        ?string $channel = null
    ): bool {
        return $this->alreadyDeliveredSuccessfully(
            $school, $context, $notificationClass, $reminderKey, $recipient, $channel
        ) || $this->hasPendingDispatch(
            $school, $context, $notificationClass, $reminderKey, $recipient, $channel
        );
    }

    public function reminderLockKey(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey,
        ?object $recipient = null,
        ?string $channel = null
    ): string {
        $recipientPart = $recipient !== null ? $this->recipientIdentity($recipient) : 'any';
        $channelPart = $channel ?? 'any';

        return 'lifecycle-reminder:'.hash('sha256', implode('|', [
            (string) $school->id,
            $context->getMorphClass(),
            (string) $context->getKey(),
            $notificationClass,
            $reminderKey,
            $recipientPart,
            $channelPart,
        ]));
    }

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

        $sent = 0;
        $payload = array_merge($extra, ['audience' => $audience]);

        foreach ($recipients as $recipient) {
            $channels = $this->channelsFor($recipient, $school);
            if ($channels === []) {
                continue;
            }

            foreach ($channels as $channel) {
                if ($audienceReminderKey) {
                    $lock = Cache::lock(
                        $this->reminderLockKey(
                            $school,
                            $context,
                            $notificationClass,
                            $audienceReminderKey,
                            $recipient,
                            $channel
                        ),
                        30
                    );

                    if (! $lock->get()) {
                        continue;
                    }

                    try {
                        if ($this->shouldSuppressReminder(
                            $school,
                            $context,
                            $notificationClass,
                            $audienceReminderKey,
                            $recipient,
                            $channel
                        )) {
                            continue;
                        }

                        $sent += $this->dispatchChannel(
                            $channel,
                            $school,
                            $context,
                            $recipient,
                            $notificationClass,
                            $preferenceKey,
                            $audienceReminderKey,
                            $payload
                        ) ? 1 : 0;
                    } finally {
                        optional($lock)->release();
                    }
                } else {
                    $sent += $this->dispatchChannel(
                        $channel,
                        $school,
                        $context,
                        $recipient,
                        $notificationClass,
                        $preferenceKey,
                        null,
                        $payload
                    ) ? 1 : 0;
                }
            }
        }

        return $sent;
    }

    protected function dispatchChannel(
        string $channel,
        School $school,
        Model $context,
        object $recipient,
        string $notificationClass,
        string $preferenceKey,
        ?string $reminderKey,
        array $payload
    ): bool {
        if ($channel === 'mail') {
            return $this->dispatchMail(
                $school,
                $context,
                $recipient,
                $notificationClass,
                $preferenceKey,
                $reminderKey,
                $payload
            );
        }
        if ($channel === 'sms') {
            return $this->dispatchSms(
                $school,
                $context,
                $recipient,
                $notificationClass,
                $preferenceKey,
                $reminderKey,
                $payload
            );
        }

        return false;
    }

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
}
