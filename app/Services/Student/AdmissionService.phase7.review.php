<?php

namespace App\Services\Student;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Notifications\Student\AdmissionAcceptedNotification;
use App\Notifications\Student\AdmissionDeclinedNotification;
use App\Notifications\Student\AdmissionExpiredNotification;
use App\Notifications\Student\AdmissionOfferedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * AdmissionService – Phase 3 Admission / offer lifecycle.
 *
 * Creates offers from approved Applications or as direct admissions.
 * Does NOT create Student or Enrollment (Phase 4).
 * Acceptance/decline/expiry are transactional state transitions with locking.
 */
class AdmissionService
{
    public function __construct(
        protected StudentApplicationService $applicationService,
        protected ?LifecycleNotificationService $lifecycleNotifications = null
    ) {
        $this->lifecycleNotifications = $lifecycleNotifications ?? app(LifecycleNotificationService::class);
    }

    public function createFromApplication(
        StudentApplication $application,
        School $school,
        User $actor,
        array $options = []
    ): Admission {
        return DB::transaction(function () use ($application, $school, $actor, $options) {
            $lockedApp = StudentApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApp->school_id !== $school->id) {
                throw ValidationException::withMessages([
                    'application_id' => 'Application does not belong to the current school.',
                ]);
            }

            if ($lockedApp->canonical_status !== StudentApplication::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'application_id' => 'Only approved applications can produce an admission offer.',
                ]);
            }

            if (! $lockedApp->isApplicationFeeSatisfied()) {
                throw ValidationException::withMessages([
                    'application_id' => 'Application fee must be paid or waived before issuing an admission offer.',
                ]);
            }

            $existingActive = Admission::query()
                ->where('application_id', $lockedApp->id)
                ->where('school_id', $school->id)
                ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'application_id' => 'An active admission offer already exists for this application.',
                ]);
            }

            $classLevelId = $options['class_level_id'] ?? $lockedApp->class_level_id;
            $sessionId = $options['academic_session_id'] ?? $lockedApp->academic_session_id;

            if (! $classLevelId) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'An offered class level is required when issuing an admission.',
                ]);
            }
            if (! $sessionId) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'An academic session is required when issuing an admission.',
                ]);
            }

            $this->assertRelatedBelongToSchool($school, $classLevelId, $sessionId, null);

            $admission = new Admission();
            $admission->school_id = $school->id;
            $admission->application_id = $lockedApp->id;
            $admission->student_id = null;
            $admission->class_level_id = $classLevelId;
            $admission->academic_session_id = $sessionId;
            $admission->school_section_id = null;
            $admission->status = Admission::STATUS_OFFERED;
            $admission->offered_at = now();
            $admission->acceptance_deadline = isset($options['acceptance_deadline'])
                ? \Illuminate\Support\Carbon::parse($options['acceptance_deadline'])
                : null;
            $admission->registration_date = $options['registration_date'] ?? null;
            $admission->registration_starts_at = isset($options['registration_starts_at'])
                ? \Illuminate\Support\Carbon::parse($options['registration_starts_at'])
                : null;
            $admission->registration_ends_at = isset($options['registration_ends_at'])
                ? \Illuminate\Support\Carbon::parse($options['registration_ends_at'])
                : null;
            $admission->notes = $options['notes'] ?? null;
            $admission->configs = array_merge($options['configs'] ?? [], [
                'created_via' => 'application',
                'created_by' => $actor->id,
            ]);

            if ($admission->acceptance_deadline && $admission->acceptance_deadline->lte(now())) {
                throw ValidationException::withMessages([
                    'acceptance_deadline' => 'Acceptance deadline must be in the future when issuing an offer.',
                ]);
            }

            $admission->save();

            $this->notifyOffered($admission);

            activity()
                ->performedOn($admission)
                ->causedBy($actor)
                ->withProperties([
                    'application_id' => $lockedApp->id,
                    'path' => 'application',
                ])
                ->log('admission.offered');

            Log::info('Admission offered from application', [
                'admission_id' => $admission->id,
                'application_id' => $lockedApp->id,
                'school_id' => $school->id,
                'actor_id' => $actor->id,
            ]);

            return $admission->fresh(['application', 'classLevel', 'academicSession']);
        });
    }

    public function createDirect(
        School $school,
        User $actor,
        array $data
    ): Admission {
        return DB::transaction(function () use ($school, $actor, $data) {
            if ($this->applicationService->applicationsRequired($school)) {
                throw ValidationException::withMessages([
                    'application_id' => 'This school requires an application before admission. Use the application-based or authorized bypass path.',
                ]);
            }

            $classLevelId = $data['class_level_id'] ?? null;
            $sessionId = $data['academic_session_id'] ?? null;

            if (! $classLevelId || ! $sessionId) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'Class level and academic session are required for direct admission.',
                ]);
            }

            $this->assertRelatedBelongToSchool($school, $classLevelId, $sessionId, null);

            $admission = new Admission();
            $admission->school_id = $school->id;
            $admission->application_id = null;
            $admission->student_id = null;
            $admission->class_level_id = $classLevelId;
            $admission->academic_session_id = $sessionId;
            $admission->school_section_id = null;
            $admission->status = Admission::STATUS_OFFERED;
            $admission->offered_at = now();
            $admission->acceptance_deadline = isset($data['acceptance_deadline'])
                ? \Illuminate\Support\Carbon::parse($data['acceptance_deadline'])
                : null;
            $admission->registration_date = $data['registration_date'] ?? null;
            $admission->registration_starts_at = isset($data['registration_starts_at'])
                ? \Illuminate\Support\Carbon::parse($data['registration_starts_at'])
                : null;
            $admission->registration_ends_at = isset($data['registration_ends_at'])
                ? \Illuminate\Support\Carbon::parse($data['registration_ends_at'])
                : null;
            $admission->notes = $data['notes'] ?? null;
            $admission->configs = array_merge($data['configs'] ?? [], [
                'created_via' => 'direct',
                'created_by' => $actor->id,
                'candidate' => [
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                ],
            ]);

            if ($admission->acceptance_deadline && $admission->acceptance_deadline->lte(now())) {
                throw ValidationException::withMessages([
                    'acceptance_deadline' => 'Acceptance deadline must be in the future when issuing an offer.',
                ]);
            }

            $admission->save();

            $this->notifyOffered($admission);

            activity()
                ->performedOn($admission)
                ->causedBy($actor)
                ->withProperties(['path' => 'direct'])
                ->log('admission.offered.direct');

            Log::info('Direct admission offered', [
                'admission_id' => $admission->id,
                'school_id' => $school->id,
                'actor_id' => $actor->id,
            ]);

            return $admission->fresh(['classLevel', 'academicSession']);
        });
    }

    public function createWalkInImmediate(
        StudentApplication $application,
        School $school,
        User $actor,
        array $options = []
    ): Admission {
        return DB::transaction(function () use ($application, $school, $actor, $options) {
            $lockedApp = StudentApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApp->school_id !== $school->id) {
                throw ValidationException::withMessages([
                    'application_id' => 'Application does not belong to the current school.',
                ]);
            }

            if ($lockedApp->canonical_status !== StudentApplication::STATUS_APPROVED) {
                if (! in_array($lockedApp->canonical_status, [
                    StudentApplication::STATUS_SUBMITTED,
                    StudentApplication::STATUS_UNDER_REVIEW,
                    StudentApplication::STATUS_DRAFT,
                ], true)) {
                    throw ValidationException::withMessages([
                        'application_id' => 'Application cannot be approved from its current state.',
                    ]);
                }

                // Use setAttribute to avoid HasDynamicEnum __set validation when enums are not seeded.
                $lockedApp->setAttribute('status', StudentApplication::STATUS_APPROVED);
                $lockedApp->setAttribute('reviewed_by', $actor->id);
                $lockedApp->setAttribute('reviewed_at', now());
                $lockedApp->setAttribute(
                    'admin_notes',
                    trim(
                        ($lockedApp->admin_notes ? $lockedApp->admin_notes."\n" : '')
                        .'Immediate approval / walk-in bypass by user '.$actor->id
                    )
                );
                $lockedApp->save();

                activity()
                    ->performedOn($lockedApp)
                    ->causedBy($actor)
                    ->withProperties(['path' => 'walk_in_bypass'])
                    ->log('application.approved.bypass');
            }

            return $this->createFromApplication($lockedApp, $school, $actor, array_merge($options, [
                'configs' => array_merge($options['configs'] ?? [], [
                    'walk_in_bypass' => true,
                    'bypass_by' => $actor->id,
                ]),
            ]));
        });
    }

    public function accept(Admission $admission, ?User $actor = null): Admission
    {
        // Expiry-on-accept must commit before the validation exception is thrown,
        // otherwise the outer transaction would roll back the expiry side effects.
        $outcome = DB::transaction(function () use ($admission, $actor) {
            $locked = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canBeAccepted()) {
                if ($locked->isPastDeadline()) {
                    $this->applyExpiryTransition($locked);

                    return ['expired' => true, 'admission' => $locked->fresh()];
                }

                throw ValidationException::withMessages([
                    'status' => 'This admission cannot be accepted in its current state.',
                ]);
            }

            $locked->transitionTo(Admission::STATUS_ACCEPTED);
            $locked->accepted_at = now();
            $locked->save();

            $this->notifyAccepted($locked);

            if ($actor) {
                activity()
                    ->performedOn($locked)
                    ->causedBy($actor)
                    ->log('admission.accepted');
            }

            Log::info('Admission accepted', [
                'admission_id' => $locked->id,
                'school_id' => $locked->school_id,
            ]);

            return ['expired' => false, 'admission' => $locked->fresh()];
        });

        if ($outcome['expired']) {
            throw ValidationException::withMessages([
                'status' => 'This offer has passed its acceptance deadline and cannot be accepted.',
            ]);
        }

        return $outcome['admission'];
    }

    public function decline(Admission $admission, ?User $actor = null, ?string $reason = null): Admission
    {
        return DB::transaction(function () use ($admission, $actor, $reason) {
            $locked = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canBeDeclined()) {
                throw ValidationException::withMessages([
                    'status' => 'This admission cannot be declined in its current state.',
                ]);
            }

            $locked->transitionTo(Admission::STATUS_DECLINED);
            $locked->declined_at = now();
            if ($reason) {
                $locked->notes = trim(($locked->notes ? $locked->notes."\n" : '').'Decline reason: '.$reason);
            }
            $locked->save();

            $this->notifyDeclined($locked);

            if ($actor) {
                activity()
                    ->performedOn($locked)
                    ->causedBy($actor)
                    ->withProperties(['reason' => $reason])
                    ->log('admission.declined');
            }

            return $locked->fresh();
        });
    }

    public function cancel(Admission $admission, User $actor, ?string $reason = null): Admission
    {
        return DB::transaction(function () use ($admission, $actor, $reason) {
            $locked = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canTransitionTo(Admission::STATUS_CANCELLED)) {
                throw ValidationException::withMessages([
                    'status' => 'This admission cannot be cancelled in its current state.',
                ]);
            }

            $locked->transitionTo(Admission::STATUS_CANCELLED);
            $locked->cancelled_at = now();
            if ($reason) {
                $locked->notes = trim(($locked->notes ? $locked->notes."\n" : '').'Cancellation: '.$reason);
            }
            $locked->save();

            activity()
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties(['reason' => $reason])
                ->log('admission.cancelled');

            return $locked->fresh();
        });
    }

    public function expire(Admission $admission): Admission
    {
        return DB::transaction(function () use ($admission) {
            $locked = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Admission::STATUS_EXPIRED) {
                return $locked;
            }

            if (! $locked->canExpire()) {
                return $locked;
            }

            $this->applyExpiryTransition($locked);

            return $locked->fresh();
        });
    }

    /**
     * Centralized expiry transition + side effects (notification + activity log).
     * Caller must hold a lock on the admission row and ensure canExpire() (or already past deadline).
     */
    protected function applyExpiryTransition(Admission $locked): void
    {
        if ($locked->status === Admission::STATUS_EXPIRED) {
            return;
        }

        if (! $locked->canTransitionTo(Admission::STATUS_EXPIRED)) {
            return;
        }

        $locked->transitionTo(Admission::STATUS_EXPIRED);
        $locked->expired_at = now();
        $locked->save();

        $this->notifyExpired($locked);

        activity()
            ->performedOn($locked)
            ->withProperties(['source' => 'expiry'])
            ->log('admission.expired');
    }

    public function processExpiries(?School $school = null): int
    {
        $query = Admission::query()
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->whereNotNull('acceptance_deadline')
            ->where('acceptance_deadline', '<=', now());

        if ($school) {
            $query->where('school_id', $school->id);
        }

        $count = 0;
        $query->orderBy('id')->chunkById(100, function ($admissions) use (&$count) {
            foreach ($admissions as $admission) {
                $result = $this->expire($admission);
                if ($result->status === Admission::STATUS_EXPIRED) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function processDeadlineReminders(int $withinHours = 48, ?School $school = null): int
    {
        $deadline = now()->addHours($withinHours);

        $query = Admission::query()
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->whereNotNull('acceptance_deadline')
            ->where('acceptance_deadline', '>', now())
            ->where('acceptance_deadline', '<=', $deadline);

        if ($school) {
            $query->where('school_id', $school->id);
        }

        $count = 0;
        $query->orderBy('id')->chunkById(100, function ($admissions) use (&$count) {
            foreach ($admissions as $admission) {
                if (! $admission->isOfferActive()) {
                    continue;
                }
                $schoolModel = School::query()->find($admission->school_id);
                if (! $schoolModel) {
                    continue;
                }

                $sent = $this->lifecycleNotifications->notify(
                    $schoolModel,
                    'admission_deadline_reminder',
                    AdmissionOfferedNotification::class,
                    $admission,
                    [
                        'reminder' => true,
                        'reminder_key' => 'admission_acceptance:'.$admission->id,
                    ]
                );
                if ($sent > 0) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Remind accepted admissions whose registration window is approaching/ending.
     * Skips admissions that already have an enrollment or left accepted status.
     */
    public function processRegistrationWindowReminders(int $withinHours = 72, ?School $school = null): int
    {
        $deadline = now()->addHours($withinHours);

        $query = Admission::query()
            ->where('status', Admission::STATUS_ACCEPTED)
            ->whereNotNull('registration_ends_at')
            ->where('registration_ends_at', '>', now())
            ->where('registration_ends_at', '<=', $deadline)
            ->whereDoesntHave('enrollment');

        if ($school) {
            $query->where('school_id', $school->id);
        }

        $count = 0;
        $query->orderBy('id')->chunkById(100, function ($admissions) use (&$count) {
            foreach ($admissions as $admission) {
                $schoolModel = School::query()->find($admission->school_id);
                if (! $schoolModel) {
                    continue;
                }

                $sent = $this->lifecycleNotifications->notify(
                    $schoolModel,
                    'admission_deadline_reminder',
                    AdmissionOfferedNotification::class,
                    $admission,
                    [
                        'reminder' => true,
                        'registration_window' => true,
                        'reminder_key' => 'admission_registration:'.$admission->id,
                    ]
                );
                if ($sent > 0) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function updateDeadlines(
        Admission $admission,
        User $actor,
        array $data
    ): Admission {
        return DB::transaction(function () use ($admission, $actor, $data) {
            $locked = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOfferActive()) {
                throw ValidationException::withMessages([
                    'status' => 'Deadlines can only be changed while the offer is active.',
                ]);
            }

            if (array_key_exists('acceptance_deadline', $data)) {
                $locked->acceptance_deadline = $data['acceptance_deadline']
                    ? \Illuminate\Support\Carbon::parse($data['acceptance_deadline'])
                    : null;
                $locked->reminder_sent_at = null;
            }
            if (array_key_exists('registration_date', $data)) {
                $locked->registration_date = $data['registration_date'];
            }
            if (array_key_exists('registration_starts_at', $data)) {
                $locked->registration_starts_at = $data['registration_starts_at']
                    ? \Illuminate\Support\Carbon::parse($data['registration_starts_at'])
                    : null;
            }
            if (array_key_exists('registration_ends_at', $data)) {
                $locked->registration_ends_at = $data['registration_ends_at']
                    ? \Illuminate\Support\Carbon::parse($data['registration_ends_at'])
                    : null;
            }

            $locked->save();

            activity()
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties($data)
                ->log('admission.deadlines_updated');

            return $locked->fresh();
        });
    }

    protected function assertRelatedBelongToSchool(
        School $school,
        ?string $classLevelId,
        ?string $sessionId,
        ?string $sectionId
    ): void {
        if ($classLevelId) {
            // ClassLevel is scoped via school_section → school (no direct school_id).
            $ok = ClassLevel::query()
                ->whereKey($classLevelId)
                ->whereHas('schoolSection', fn ($q) => $q->where('school_id', $school->id))
                ->exists();
            if (! $ok) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'Class level does not belong to the current school.',
                ]);
            }
        }
        if ($sessionId) {
            $ok = AcademicSession::query()->whereKey($sessionId)->where('school_id', $school->id)->exists();
            if (! $ok) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session does not belong to the current school.',
                ]);
            }
        }
        if ($sectionId) {
            $ok = \App\Models\SchoolSection::query()->whereKey($sectionId)->where('school_id', $school->id)->exists();
            if (! $ok) {
                throw ValidationException::withMessages([
                    'school_section_id' => 'School section does not belong to the current school.',
                ]);
            }
        }
    }

    protected function notifyOffered(Admission $admission): void
    {
        $this->lifecycleNotify($admission, 'admission_offered', AdmissionOfferedNotification::class);
    }

    protected function notifyAccepted(Admission $admission): void
    {
        $this->lifecycleNotify($admission, 'admission_accepted', AdmissionAcceptedNotification::class);
    }

    protected function notifyDeclined(Admission $admission): void
    {
        $this->lifecycleNotify($admission, 'admission_declined', AdmissionDeclinedNotification::class);
    }

    protected function notifyExpired(Admission $admission): void
    {
        $this->lifecycleNotify($admission, 'admission_expired', AdmissionExpiredNotification::class);
    }

    protected function lifecycleNotify(Admission $admission, string $preferenceKey, string $notificationClass, array $extra = []): void
    {
        $school = School::query()->find($admission->school_id);
        if (! $school) {
            return;
        }

        $this->lifecycleNotifications->notify($school, $preferenceKey, $notificationClass, $admission, $extra);
    }
}

