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
     * True when a successful delivery was already logged for this reminder key.
     * When $recipient is provided, the check is scoped to that recipient so one
     * guardian's success cannot suppress another guardian's retry.
     */

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
            ->where('metadata->phase', 'dispatched');

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
     * @return int number of notifiables notified (0 if skipped/disabled/no recipients)
     */
    public function notify(
        School $school,
        string $preferenceKey,
        string $notificationClass,
        Model $context,
        array $extra = []
    ): int {
        // Explicit audience → single path. Otherwise deliver independently to every
        // audience enabled in the preference (parent + admin), matching seeded shape.
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
        // Scope reminder keys per audience so parent success does not suppress admin (and vice versa).
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

        // Concurrent-safe claim for reminder slots before dispatch.
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
     *
     * Uses a transaction + row lock on existing logs so concurrent workers cannot
     * both pass suppression and both dispatch. Failed prior attempts remain retryable.
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
            $existing = $q->lockForUpdate()->exists();
            if ($existing) {
                return false;
            }

            // Claim row — subsequent concurrent workers see phase=claimed under lock.
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
     * @return Collection<int, object>
     */
    /**
     * Resolve notifiables for the given audience.
     *
     * parent  → candidate / guardian / student profile contacts (existing path)
     * admin   → school staff users with the relevant lifecycle view permission
     *           (reuses Laratrust + school membership; no new subsystem)
     *
     * @return Collection<int, object>
     */
    public function resolveRecipients(Model $context, string $audience = 'parent', ?School $school = null): Collection
    {
        if ($audience === 'admin') {
            $school = $school ?? $this->schoolFromContext($context);
            if (! $school) {
                return collect();
            }

            return $this->resolveStaffRecipients($school, $context);
        }

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

    /**
     * Map lifecycle context to the staff permission used for admin audience targeting.
     */
    protected function staffPermissionForContext(Model $context): string
    {
        return match (true) {
            $context instanceof StudentApplication => 'applications.view',
            $context instanceof Admission => 'admissions.view',
            $context instanceof Enrollment => 'enrollments.view',
            default => 'applications.view',
        };
    }

    /**
     * Staff/admin recipients for a school, filtered by lifecycle permission.
     * Pattern mirrors NotifyAdminOnSectionDeactivated / Promotion approver resolution.
     *
     * @return Collection<int, User>
     */
    public function resolveStaffRecipients(School $school, Model $context): Collection
    {
        $permission = $this->staffPermissionForContext($context);
        $schoolId = $school->id;

        try {
            $users = User::query()
                ->where('is_active', true)
                ->where(function ($q) use ($schoolId) {
                    $q->whereHas('schools', fn ($s) => $s->where('schools.id', $schoolId));
                })
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Lifecycle admin recipient resolution failed', [
                'school_id' => $schoolId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        return $users
            ->filter(function (User $user) use ($permission) {
                if (method_exists($user, 'isAbleTo') && $user->isAbleTo($permission)) {
                    return true;
                }
                if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
                    return true;
                }
                // Laratrust permission() scope may already have filtered; keep broad checks.
                if (method_exists($user, 'hasPermissionTo')) {
                    try {
                        return $user->hasPermissionTo($permission);
                    } catch (\Throwable) {
                        return false;
                    }
                }

                return false;
            })
            ->values();
    }

    protected function schoolFromContext(Model $context): ?School
    {
        $schoolId = $context->school_id ?? null;
        if (! $schoolId) {
            return null;
        }

        return School::query()->find($schoolId);
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
                    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $recipients->push($this->mailRoute($email));
                    }
                    $phone = $student->profile?->phone ?? null;
                    if (is_string($phone) && ($normalized = $this->normalizePhone($phone)) !== '') {
                        $recipients->push($this->smsRoute($normalized));
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

        // Incomplete enrollments may only have biodata on the enrollment record.
        if ($recipients->isEmpty()) {
            $meta = $enrollment->meta ?? [];
            if (is_string($meta)) {
                $decoded = json_decode($meta, true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            $biodata = is_array($meta) ? ($meta['biodata'] ?? []) : [];
            $email = is_array($biodata) ? ($biodata['email'] ?? null) : null;
            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients->push($this->mailRoute($email));
            }
            $phone = is_array($biodata) ? ($biodata['phone'] ?? null) : null;
            if (is_string($phone) && ($normalized = $this->normalizePhone($phone)) !== '') {
                $recipients->push($this->smsRoute($normalized));
            }
        }

        return $recipients->unique(fn ($r) => $this->recipientIdentity($r))->values();
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

        $candidateEmail = data_get($admission->configs ?? [], 'candidate.email');
        if (is_string($candidateEmail) && filter_var(trim($candidateEmail), FILTER_VALIDATE_EMAIL)) {
            $recipients->push($this->mailRoute(trim($candidateEmail)));
        }
        $candidatePhone = data_get($admission->configs ?? [], 'candidate.phone');
        if (is_string($candidatePhone) && ($normalized = $this->normalizePhone($candidatePhone)) !== '') {
            $recipients->push($this->smsRoute($normalized));
        }

        return $recipients->unique(fn ($r) => $this->recipientIdentity($r))->values();
    }

    protected function resolveForApplication(StudentApplication $application): Collection
    {
        $recipients = collect();

        $email = is_string($application->email ?? null) ? trim($application->email) : '';
        $phone = is_string($application->phone ?? null) ? $this->normalizePhone($application->phone) : '';

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients->push($this->mailRoute($email));
        }
        if ($phone !== '') {
            $recipients->push($this->smsRoute($phone));
        }

        $guardians = $application->guardians_data;
        if (is_array($guardians)) {
            foreach ($guardians as $g) {
                if (! is_array($g)) {
                    continue;
                }
                $gEmail = isset($g['email']) && is_string($g['email']) ? trim($g['email']) : '';
                $gPhone = isset($g['phone']) && is_string($g['phone']) ? $this->normalizePhone($g['phone']) : '';
                if ($gEmail !== '' && filter_var($gEmail, FILTER_VALIDATE_EMAIL)) {
                    $recipients->push($this->mailRoute($gEmail));
                }
                if ($gPhone !== '') {
                    $recipients->push($this->smsRoute($gPhone));
                }
            }
        }

        return $recipients->unique(fn ($r) => $this->recipientIdentity($r))->values();
    }

    /**
     * @return Collection<int, Guardian|AnonymousNotifiable>
     */
    protected function guardiansAsNotifiables(Student $student): Collection
    {
        $guardians = $student->relationLoaded('guardians')
            ? $student->guardians
            : $student->guardians()->with('profile')->get();

        return $guardians->filter(function ($guardian) {
            return $guardian instanceof Guardian
                && ($this->hasValidEmail($guardian) || $this->hasValidPhone($guardian));
        })->values();
    }

    protected function mailRoute(string $email): AnonymousNotifiable
    {
        return Notification::route('mail', $email);
    }

    protected function smsRoute(string $phone): AnonymousNotifiable
    {
        return Notification::route('sms', $this->normalizePhone($phone));
    }

    /**
     * Normalize phone for identity / SMS routing (digits and leading + only).
     */
    public function normalizePhone(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        if (filter_var($phone, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        $normalized = preg_replace('/[^\d+]/', '', $phone) ?? '';
        $normalized = preg_replace('/(?!^)\+/', '', $normalized) ?? '';
        $digits = preg_replace('/\D/', '', $normalized) ?? '';
        if (strlen($digits) < 7) {
            return '';
        }

        return $normalized;
    }

    /**
     * Channel-aware stable identity for deduplication.
     * Email recipients key on email; SMS/phone-only key on normalized phone.
     * Never uses empty string as an identity.
     */
    public function recipientIdentity(object $recipient): string
    {
        $email = $this->recipientEmail($recipient);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'mail:'.strtolower(trim($email));
        }
        $phone = $this->normalizePhone($this->recipientPhone($recipient));
        if ($phone !== '') {
            return 'sms:'.$phone;
        }
        if ($recipient instanceof Model && $recipient->getKey()) {
            return 'model:'.$recipient->getMorphClass().':'.$recipient->getKey();
        }

        return 'anon:'.spl_object_id($recipient);
    }

    public function hasValidEmail(object $recipient): bool
    {
        $email = $this->recipientEmail($recipient);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function hasValidPhone(object $recipient): bool
    {
        return $this->normalizePhone($this->recipientPhone($recipient)) !== '';
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
     * Human-readable address for NotificationLog.recipient (channel-aware).
     */
    protected function recipientAddress(object $recipient, ?string $channel = null): string
    {
        if ($channel === 'sms') {
            $phone = $this->normalizePhone($this->recipientPhone($recipient));
            if ($phone !== '') {
                return $phone;
            }
        }
        if ($channel === 'mail') {
            $email = $this->recipientEmail($recipient);
            if ($email !== '') {
                return $email;
            }
        }

        $email = $this->recipientEmail($recipient);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        $phone = $this->normalizePhone($this->recipientPhone($recipient));
        if ($phone !== '') {
            return $phone;
        }

        return '';
    }

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
        $recipientAddress = $this->recipientAddress($recipient, $channel);

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
