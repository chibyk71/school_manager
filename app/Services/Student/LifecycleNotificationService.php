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
     * Whether the school has enabled this lifecycle preference for any audience.
     */
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
            // Unspecified keys default to enabled so schools are not silent until configured.
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
     * True when a successful delivery was already logged for this reminder key.
     * When $recipient is provided, the check is scoped to that recipient so one
     * guardian's success cannot suppress another guardian's retry.
     */
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

        if ($recipient instanceof Model && $recipient->getKey()) {
            $q->where('notifiable_type', $recipient->getMorphClass())
                ->where('notifiable_id', $recipient->getKey());
        } elseif ($recipient !== null) {
            $address = $this->recipientAddress($recipient);
            if ($address !== '') {
                $q->where('recipient', $address);
            }
        }

        return $q->exists();
    }

    /**
     * Dispatch a lifecycle notification to resolved recipients.
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
        $audience = $extra['audience'] ?? 'parent';
        if (! $this->isEnabled($school, $preferenceKey, $audience)) {
            return 0;
        }

        $reminderKey = $extra['reminder_key'] ?? null;

        $recipients = $this->resolveRecipients($context);
        if ($recipients->isEmpty()) {
            Log::info('Lifecycle notification skipped: no recipients', [
                'school_id' => $school->id,
                'context' => get_class($context),
                'context_id' => $context->getKey(),
                'notification' => $notificationClass,
                'preference_key' => $preferenceKey,
                'reminder_key' => $reminderKey,
            ]);

            return 0;
        }

        // Per-recipient idempotency: only skip recipients that already have success=true
        // for this reminder (any successful channel counts as delivered for that recipient).
        if ($reminderKey) {
            $recipients = $recipients->filter(
                fn ($recipient) => ! $this->alreadyDeliveredSuccessfully(
                    $school,
                    $context,
                    $notificationClass,
                    $reminderKey,
                    $recipient
                )
            )->values();

            if ($recipients->isEmpty()) {
                return 0;
            }
        }

        $sent = 0;
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
                        $reminderKey,
                        $extra
                    ) ? 1 : 0;
                } elseif ($channel === 'sms') {
                    $sent += $this->dispatchSms(
                        $school,
                        $context,
                        $recipient,
                        $notificationClass,
                        $preferenceKey,
                        $reminderKey,
                        $extra
                    ) ? 1 : 0;
                }
            }
        }

        return $sent;
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

    protected function dispatchMail(
        School $school,
        Model $context,
        object $recipient,
        string $notificationClass,
        string $preferenceKey,
        ?string $reminderKey,
        array $extra
    ): bool {
        $mailable = $this->asMailNotifiable($recipient);
        if ($mailable === null) {
            return false;
        }

        $dispatchId = (string) Str::uuid();
        $payload = array_merge($extra, [
            'preference_key' => $preferenceKey,
            'school_id' => $school->id,
            'lifecycle_type' => $context->getMorphClass(),
            'lifecycle_id' => (string) $context->getKey(),
            'reminder_key' => $reminderKey,
            'dispatch_id' => $dispatchId,
            'channel' => 'mail',
        ]);

        try {
            $notification = new $notificationClass($context, $payload);
            Notification::send($mailable, $notification);

            $isFake = Notification::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\NotificationFake;

            $this->logDispatch(
                $school,
                $context,
                $mailable,
                $notificationClass,
                'mail',
                $isFake,
                null,
                [
                    'preference_key' => $preferenceKey,
                    'reminder_key' => $reminderKey,
                    'dispatch_id' => $dispatchId,
                    'phase' => $isFake ? 'delivered' : 'dispatched',
                    'channel' => 'mail',
                ]
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Lifecycle mail notification dispatch failed', [
                'school_id' => $school->id,
                'context_id' => $context->getKey(),
                'error' => $e->getMessage(),
            ]);
            $this->logDispatch(
                $school,
                $context,
                $mailable,
                $notificationClass,
                'mail',
                false,
                $e->getMessage(),
                [
                    'preference_key' => $preferenceKey,
                    'reminder_key' => $reminderKey,
                    'dispatch_id' => $dispatchId,
                    'phase' => 'dispatch_failed',
                    'channel' => 'mail',
                ]
            );

            return false;
        }
    }

    protected function dispatchSms(
        School $school,
        Model $context,
        object $recipient,
        string $notificationClass,
        string $preferenceKey,
        ?string $reminderKey,
        array $extra
    ): bool {
        $phone = $this->recipientPhone($recipient);
        if ($phone === '') {
            return false;
        }

        $dispatchId = (string) Str::uuid();
        $message = $this->smsBody($notificationClass, $context, $extra);

        // SMS is synchronous via SmsService — log outcome immediately (not queue dispatch).
        try {
            $ok = $this->sms->send($phone, $message, $school);
            $this->logDispatch(
                $school,
                $context,
                $recipient,
                $notificationClass,
                'sms',
                $ok,
                $ok ? null : 'SMS providers failed or disabled',
                [
                    'preference_key' => $preferenceKey,
                    'reminder_key' => $reminderKey,
                    'dispatch_id' => $dispatchId,
                    'phase' => $ok ? 'delivered' : 'failed',
                    'channel' => 'sms',
                    'provider' => 'school_sms',
                ]
            );

            return $ok;
        } catch (\Throwable $e) {
            Log::warning('Lifecycle SMS notification failed', [
                'school_id' => $school->id,
                'context_id' => $context->getKey(),
                'error' => $e->getMessage(),
            ]);
            $this->logDispatch(
                $school,
                $context,
                $recipient,
                $notificationClass,
                'sms',
                false,
                $e->getMessage(),
                [
                    'preference_key' => $preferenceKey,
                    'reminder_key' => $reminderKey,
                    'dispatch_id' => $dispatchId,
                    'phase' => 'failed',
                    'channel' => 'sms',
                ]
            );

            return false;
        }
    }

    protected function smsBody(string $notificationClass, Model $context, array $extra): string
    {
        $base = class_basename($notificationClass);
        $parts = [
            'School Manager:',
            str_replace(['Notification', 'Reminder'], ['', ' reminder'], $base),
        ];
        if (! empty($extra['reminder'])) {
            $parts[] = 'Action may be required before the deadline.';
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    /**
     * Resolve notifiables from the authoritative lifecycle model.
     *
     * Order of preference:
     * 1. Linked Student guardians (Profile contact)
     * 2. Linked Student profile / user
     * 3. Application domain contact fields (pre-identity)
     * 4. Admission candidate contact via application / configs
     *
     * @return Collection<int, object>
     */
    public function resolveRecipients(Model $context): Collection
    {
        if ($context instanceof Enrollment) {
            return $this->resolveForEnrollment($context);
        }
        if ($context instanceof Admission) {
            return $this->resolveForAdmission($context);
        }
        if ($context instanceof StudentApplication) {
            return $this->resolveForApplication($context);
        }

        return collect();
    }

    protected function resolveForEnrollment(Enrollment $enrollment): Collection
    {
        $recipients = collect();

        if ($enrollment->student_id) {
            $student = $enrollment->relationLoaded('student')
                ? $enrollment->student
                : Student::query()->with(['guardians.profile', 'profile'])->find($enrollment->student_id);

            if ($student) {
                $recipients = $recipients->merge($this->guardiansAsNotifiables($student));
                if ($recipients->isEmpty()) {
                    $email = $student->profile?->email;
                    if ($email) {
                        $recipients->push($this->mailRoute($email));
                    }
                }
            }
        }

        if ($recipients->isEmpty() && $enrollment->admission_id) {
            $admission = $enrollment->relationLoaded('admission')
                ? $enrollment->admission
                : Admission::query()->with('application')->find($enrollment->admission_id);
            if ($admission) {
                $recipients = $recipients->merge($this->resolveForAdmission($admission));
            }
        }

        return $recipients->unique(fn ($r) => $this->recipientAddress($r))->values();
    }

    protected function resolveForAdmission(Admission $admission): Collection
    {
        $recipients = collect();

        $application = $admission->relationLoaded('application')
            ? $admission->application
            : ($admission->application_id
                ? StudentApplication::query()->find($admission->application_id)
                : null);

        if ($application) {
            $recipients = $recipients->merge($this->resolveForApplication($application));
        }

        // Domain candidate snapshot on admission configs (existing AdmissionService pattern)
        $candidateEmail = data_get($admission->configs ?? [], 'candidate.email');
        if ($candidateEmail && filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients->push($this->mailRoute($candidateEmail));
        }

        return $recipients->unique(fn ($r) => $this->recipientAddress($r))->values();
    }

    protected function resolveForApplication(StudentApplication $application): Collection
    {
        $recipients = collect();

        // Authoritative application contact columns (not arbitrary meta)
        if ($application->email && filter_var($application->email, FILTER_VALIDATE_EMAIL)) {
            $recipients->push($this->mailRoute($application->email));
        }

        // Domain guardians_data is the pre-identity guardian capture on the application
        $guardians = $application->guardians_data;
        if (is_array($guardians)) {
            foreach ($guardians as $g) {
                $email = is_array($g) ? ($g['email'] ?? null) : null;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients->push($this->mailRoute($email));
                }
            }
        }

        return $recipients->unique(fn ($r) => $this->recipientAddress($r))->values();
    }

    /**
     * @return Collection<int, Guardian|AnonymousNotifiable>
     */
    protected function guardiansAsNotifiables(Student $student): Collection
    {
        $guardians = $student->relationLoaded('guardians')
            ? $student->guardians
            : $student->guardians()->with('profile')->get();

        // Keep guardians with email and/or phone; channel selection happens at dispatch.
        return $guardians->filter(function ($guardian) {
            return $guardian instanceof Guardian
                && ($this->hasValidEmail($guardian) || $this->hasValidPhone($guardian));
        })->values();
    }

    protected function mailRoute(string $email): AnonymousNotifiable
    {
        return Notification::route('mail', $email);
    }

    /**
     * True when the notifiable exposes a valid email suitable for the mail channel.
     */
    public function hasValidEmail(object $recipient): bool
    {
        $email = $this->recipientEmail($recipient);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * True when the notifiable exposes a phone suitable for SMS (not used as mail).
     */
    public function hasValidPhone(object $recipient): bool
    {
        $phone = $this->recipientPhone($recipient);

        return $phone !== '' && ! filter_var($phone, FILTER_VALIDATE_EMAIL);
    }

    public function recipientEmail(object $recipient): string
    {
        if ($recipient instanceof AnonymousNotifiable) {
            $route = $recipient->routeNotificationFor('mail');

            return is_string($route) ? $route : '';
        }
        if ($recipient instanceof Guardian) {
            return (string) ($recipient->profile?->email ?? '');
        }
        if ($recipient instanceof User) {
            return (string) ($recipient->email ?? '');
        }
        if (method_exists($recipient, 'routeNotificationFor')) {
            $route = $recipient->routeNotificationFor('mail');

            return is_string($route) ? $route : '';
        }

        return '';
    }

    public function recipientPhone(object $recipient): string
    {
        if ($recipient instanceof Guardian) {
            return (string) ($recipient->profile?->phone ?? '');
        }
        if ($recipient instanceof User && property_exists($recipient, 'phone')) {
            return (string) ($recipient->phone ?? '');
        }
        if ($recipient instanceof AnonymousNotifiable) {
            $route = $recipient->routeNotificationFor('sms');

            return is_string($route) ? $route : '';
        }

        return '';
    }

    /**
     * Build a mail-only notifiable. Phone-only recipients return null.
     */
    protected function asMailNotifiable(object $recipient): ?object
    {
        if (! $this->hasValidEmail($recipient)) {
            return null;
        }

        if ($recipient instanceof Guardian || $recipient instanceof User) {
            return $recipient;
        }

        $email = $this->recipientEmail($recipient);

        return $this->mailRoute($email);
    }

    /**
     * Address used for NotificationLog.recipient — email for mail channel.
     */
    protected function recipientAddress(object $recipient): string
    {
        $email = $this->recipientEmail($recipient);
        if ($email !== '') {
            return $email;
        }

        // Do not fall back to phone for mail-oriented logging identity when email is absent
        // and we are not on an SMS path.
        return '';
    }

    /**
     * Record a dispatch or delivery attempt.
     * notifiable_* is the actual recipient model when available (Guardian/User).
     * Lifecycle correlation lives in metadata (lifecycle_type, lifecycle_id, reminder_key).
     * notification_id is unused (0); correlation lives in metadata.dispatch_id.
     */
    protected function logDispatch(
        School $school,
        Model $context,
        object $recipient,
        string $notificationClass,
        string $channel,
        bool $success,
        ?string $error,
        array $metadata = []
    ): void {
        $recipientAddress = $this->recipientAddress($recipient);

        // Prefer real recipient models as notifiable; fall back to lifecycle context.
        if ($recipient instanceof Model && $recipient->getKey()) {
            $notifiableType = $recipient->getMorphClass();
            $notifiableId = $recipient->getKey();
        } else {
            $notifiableType = $context->getMorphClass();
            $notifiableId = $context->getKey();
        }

        $meta = array_merge([
            'lifecycle_type' => $context->getMorphClass(),
            'lifecycle_id' => (string) $context->getKey(),
            'recipient_address' => $recipientAddress,
            'recipient_class' => is_object($recipient) ? get_class($recipient) : null,
        ], $metadata);

        $meta['dispatch_id'] = ! empty($meta['dispatch_id']) ? $meta['dispatch_id'] : (string) Str::uuid();

        try {
            NotificationLog::create([
                'school_id' => $school->id,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'notification_type' => $notificationClass,
                'notification_id' => 0,
                'channel' => $channel,
                'provider' => $channel === 'mail' ? 'mail' : null,
                'recipient' => $recipientAddress !== '' ? $recipientAddress : 'unknown',
                'message' => class_basename($notificationClass)
                    .(! empty($meta['reminder_key']) ? ' ['.$meta['reminder_key'].']' : ''),
                'success' => $success,
                'error' => $error,
                'segments' => 1,
                'metadata' => $meta,
                'delivered_at' => $success ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write NotificationLog for lifecycle notification', [
                'error' => $e->getMessage(),
                'notification' => $notificationClass,
            ]);
        }
    }

    /**
     * Mark successful delivery (creates a success row when no prior dispatch_id exists).
     * Prefer markDispatchOutcome() when a dispatch_id is known so the pending row is updated.
     */
    public function markDelivered(
        School $school,
        Model $context,
        object $recipient,
        string $notificationClass,
        string $channel = 'mail',
        ?string $reminderKey = null,
        array $metadata = []
    ): void {
        $dispatchId = $metadata['dispatch_id'] ?? null;
        if (is_string($dispatchId) && $dispatchId !== '') {
            $this->markDispatchOutcome($dispatchId, true, null);

            return;
        }

        $this->logDispatch(
            $school,
            $context,
            $recipient,
            $notificationClass,
            $channel,
            true,
            null,
            array_merge($metadata, [
                'reminder_key' => $reminderKey,
                'phase' => 'delivered',
            ])
        );
    }

    /**
     * Update the NotificationLog row for a specific dispatch_id after channel outcome.
     * Used by LogLifecycleNotificationDelivery on NotificationSent / NotificationFailed.
     */
    public function markDispatchOutcome(string $dispatchId, bool $success, ?string $error = null): bool
    {
        $log = NotificationLog::query()
            ->where('metadata->dispatch_id', $dispatchId)
            ->latest('id')
            ->first();

        if (! $log) {
            Log::info('Lifecycle notification delivery outcome has no matching dispatch log', [
                'dispatch_id' => $dispatchId,
                'success' => $success,
            ]);

            return false;
        }

        $meta = is_array($log->metadata) ? $log->metadata : [];
        $meta['phase'] = $success ? 'delivered' : 'failed';
        if ($error) {
            $meta['channel_error'] = $error;
        }

        $log->forceFill([
            'success' => $success,
            'error' => $success ? null : ($error ?: $log->error),
            'delivered_at' => $success ? now() : null,
            'metadata' => $meta,
        ])->save();

        return true;
    }
}
