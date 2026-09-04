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
    public function isEnabled(School $school, string $preferenceKey): bool
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
            return in_array(true, $pref, true);
        }

        return (bool) $pref;
    }

    /**
     * True when a successful delivery was already logged for this reminder key.
     */
    public function alreadyDeliveredSuccessfully(
        School $school,
        Model $context,
        string $notificationClass,
        string $reminderKey
    ): bool {
        return NotificationLog::query()
            ->where('school_id', $school->id)
            ->where('notifiable_type', $context->getMorphClass())
            ->where('notifiable_id', $context->getKey())
            ->where('notification_type', $notificationClass)
            ->where('success', true)
            ->where('metadata->reminder_key', $reminderKey)
            ->exists();
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
        if (! $this->isEnabled($school, $preferenceKey)) {
            return 0;
        }

        $reminderKey = $extra['reminder_key'] ?? null;
        if ($reminderKey && $this->alreadyDeliveredSuccessfully($school, $context, $notificationClass, $reminderKey)) {
            return 0;
        }

        $recipients = $this->resolveRecipients($context);
        if ($recipients->isEmpty()) {
            $this->logAttempt($school, $context, $notificationClass, 'mail', '', false, 'No recipients resolved', [
                'preference_key' => $preferenceKey,
                'reminder_key' => $reminderKey,
            ]);

            return 0;
        }

        $sent = 0;
        foreach ($recipients as $recipient) {
            try {
                $notification = new $notificationClass($context, array_merge($extra, [
                    'preference_key' => $preferenceKey,
                    'school_id' => $school->id,
                ]));

                Notification::send($recipient, $notification);

                // Queue dispatch is not delivery success. Log "accepted for delivery"
                // with success=false until a channel confirms delivery, unless send is sync.
                $this->logAttempt(
                    $school,
                    $context,
                    $notificationClass,
                    'mail',
                    $this->recipientAddress($recipient),
                    false,
                    null,
                    [
                        'preference_key' => $preferenceKey,
                        'reminder_key' => $reminderKey,
                        'phase' => 'dispatched',
                        'notifiable_class' => is_object($recipient) ? get_class($recipient) : null,
                    ]
                );

                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Lifecycle notification dispatch failed', [
                    'school_id' => $school->id,
                    'context' => get_class($context),
                    'context_id' => $context->getKey(),
                    'notification' => $notificationClass,
                    'error' => $e->getMessage(),
                ]);
                $this->logAttempt(
                    $school,
                    $context,
                    $notificationClass,
                    'mail',
                    $this->recipientAddress($recipient),
                    false,
                    $e->getMessage(),
                    [
                        'preference_key' => $preferenceKey,
                        'reminder_key' => $reminderKey,
                        'phase' => 'dispatch_failed',
                    ]
                );
            }
        }

        return $sent;
    }

    /**
     * Mark a prior dispatch as successfully delivered (called from channel/listener hooks).
     */
    public function markDelivered(
        School $school,
        Model $context,
        string $notificationClass,
        string $channel,
        string $recipient,
        ?string $reminderKey = null,
        array $metadata = []
    ): void {
        $this->logAttempt($school, $context, $notificationClass, $channel, $recipient, true, null, array_merge($metadata, [
            'reminder_key' => $reminderKey,
            'phase' => 'delivered',
        ]));
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

        return $guardians->filter(function ($guardian) {
            return $guardian instanceof Guardian
                && ($guardian->profile?->email || $guardian->profile?->phone);
        })->values();
    }

    protected function mailRoute(string $email): AnonymousNotifiable
    {
        return Notification::route('mail', $email);
    }

    protected function recipientAddress(object $recipient): string
    {
        if ($recipient instanceof AnonymousNotifiable) {
            return (string) ($recipient->routeNotificationFor('mail') ?? '');
        }
        if ($recipient instanceof Guardian) {
            return (string) ($recipient->profile?->email ?? $recipient->profile?->phone ?? '');
        }
        if ($recipient instanceof User) {
            return (string) ($recipient->email ?? '');
        }
        if (method_exists($recipient, 'routeNotificationFor')) {
            return (string) ($recipient->routeNotificationFor('mail') ?? '');
        }

        return '';
    }

    protected function logAttempt(
        School $school,
        Model $context,
        string $notificationClass,
        string $channel,
        string $recipient,
        bool $success,
        ?string $error,
        array $metadata = []
    ): void {
        try {
            NotificationLog::create([
                'school_id' => $school->id,
                'notifiable_type' => $context->getMorphClass(),
                'notifiable_id' => $context->getKey(),
                // notification morph: store class name as type; id uses context key as correlation
                'notification_type' => $notificationClass,
                'notification_id' => $context->getKey(),
                'channel' => $channel,
                'provider' => $channel === 'mail' ? 'mail' : null,
                'recipient' => $recipient !== '' ? $recipient : 'unknown',
                'message' => class_basename($notificationClass).($metadata['reminder_key'] ?? '' ? ' ['.$metadata['reminder_key'].']' : ''),
                'success' => $success,
                'error' => $error,
                'segments' => 1,
                'metadata' => $metadata,
                'delivered_at' => $success ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write NotificationLog for lifecycle notification', [
                'error' => $e->getMessage(),
                'notification' => $notificationClass,
            ]);
        }
    }
}
