<?php

namespace App\Services\Student\Concerns;

use App\Models\Guardian;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Dispatch, recipient resolution, and logging helpers for lifecycle notifications.
 */
trait DispatchesLifecycleNotifications
{
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
     * Resolve notifiables for the given audience.
     *
     * parent  → candidate / guardian / student profile contacts
     * admin   → school staff users with the relevant lifecycle view permission
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

        if (is_string($application->email ?? null) && filter_var(trim($application->email), FILTER_VALIDATE_EMAIL)) {
            $recipients->push($this->mailRoute(trim($application->email)));
        }
        if (is_string($application->phone ?? null) && ($normalized = $this->normalizePhone($application->phone)) !== '') {
            $recipients->push($this->smsRoute($normalized));
        }

        $guardianEmail = data_get($application->meta ?? [], 'guardian.email')
            ?? data_get($application->meta ?? [], 'parent.email');
        if (is_string($guardianEmail) && filter_var(trim($guardianEmail), FILTER_VALIDATE_EMAIL)) {
            $recipients->push($this->mailRoute(trim($guardianEmail)));
        }
        $guardianPhone = data_get($application->meta ?? [], 'guardian.phone')
            ?? data_get($application->meta ?? [], 'parent.phone');
        if (is_string($guardianPhone) && ($normalized = $this->normalizePhone($guardianPhone)) !== '') {
            $recipients->push($this->smsRoute($normalized));
        }

        return $recipients->unique(fn ($r) => $this->recipientIdentity($r))->values();
    }

    protected function guardiansAsNotifiables(Student $student): Collection
    {
        $recipients = collect();
        $guardians = $student->relationLoaded('guardians')
            ? $student->guardians
            : $student->guardians()->with('profile')->get();

        foreach ($guardians as $guardian) {
            $recipients->push($guardian);
        }

        return $recipients;
    }

    protected function mailRoute(string $email): AnonymousNotifiable
    {
        return (new AnonymousNotifiable)->route('mail', $email);
    }

    protected function smsRoute(string $phone): AnonymousNotifiable
    {
        return (new AnonymousNotifiable)->route('sms', $phone);
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

        return (string) (data_get($recipient, 'email') ?? '');
    }

    public function recipientPhone(object $recipient): string
    {
        if ($recipient instanceof AnonymousNotifiable) {
            $route = $recipient->routeNotificationFor('sms');

            return is_string($route) ? $route : '';
        }
        if ($recipient instanceof Guardian) {
            return (string) ($recipient->profile?->phone ?? '');
        }
        if ($recipient instanceof User) {
            return (string) ($recipient->phone ?? '');
        }

        return (string) (data_get($recipient, 'phone') ?? '');
    }

    public function normalizePhone(?string $phone): string
    {
        if (! is_string($phone) || $phone === '') {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $phone);

        return is_string($digits) ? $digits : '';
    }

    public function recipientIdentity(object $recipient): string
    {
        if ($recipient instanceof Model && $recipient->getKey()) {
            return $recipient->getMorphClass().':'.$recipient->getKey();
        }
        $email = $this->recipientEmail($recipient);
        if ($email !== '') {
            return 'mail:'.strtolower(trim($email));
        }
        $phone = $this->normalizePhone($this->recipientPhone($recipient));
        if ($phone !== '') {
            return 'sms:'.$phone;
        }

        return spl_object_hash($recipient);
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

    protected function recipientAddress(object $recipient, ?string $channel = null): string
    {
        if ($channel === 'sms') {
            $phone = $this->normalizePhone($this->recipientPhone($recipient));

            return $phone !== '' ? 'sms:'.$phone : '';
        }

        $email = $this->recipientEmail($recipient);
        if ($email !== '') {
            return 'mail:'.strtolower(trim($email));
        }

        return $this->recipientIdentity($recipient);
    }

    protected function logDispatch(
        School $school,
        Model $context,
        object $recipient,
        string $notificationClass,
        string $channel,
        bool $success,
        ?string $error = null,
        array $meta = []
    ): void {
        try {
            $recipientAddress = $this->recipientAddress($recipient, $channel);
            $notifiableType = null;
            $notifiableId = null;
            if ($recipient instanceof Model && $recipient->getKey()) {
                $notifiableType = $recipient->getMorphClass();
                $notifiableId = $recipient->getKey();
            }

            NotificationLog::query()->create([
                'school_id' => $school->id,
                'notification_type' => $notificationClass,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'channel' => $channel,
                'provider' => $channel === 'mail' ? 'mail' : null,
                'recipient' => $recipientAddress !== '' ? $recipientAddress : 'unknown',
                'message' => class_basename($notificationClass)
                    .(! empty($meta['reminder_key']) ? ' ['.$meta['reminder_key'].']' : ''),
                'success' => $success,
                'error' => $error,
                'segments' => 1,
                'metadata' => array_merge($meta, [
                    'lifecycle_type' => $context->getMorphClass(),
                    'lifecycle_id' => (string) $context->getKey(),
                ]),
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
