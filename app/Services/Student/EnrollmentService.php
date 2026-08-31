<?php

namespace App\Services\Student;

use App\Models\Academic\AcademicSession;
use App\Models\Profile;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\EnrollmentRequirementDefinition;
use App\Models\Student\EnrollmentRequirementInstance;
use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * EnrollmentService – Phase 4 Enrollment lifecycle.
 *
 * Critical boundaries:
 * - Enrollment may exist without Student (student_id null until finalize).
 * - Finalization is transactional + concurrency-safe (row lock + readiness re-check).
 * - Profile is the permanent owner of person biodata; Enrollment.meta is workflow-only.
 * - Profile resolution is conservative: exact identifiers only; ambiguous/missing identity
 *   requires explicit staff-supplied profile_id (no silent duplicate persons).
 * - Student is school-scoped capacity for a Profile: at most one Student per (school, profile).
 * - Admission is optional; when present, one-Enrollment-per-Admission is preserved.
 * - Requirements are authoritative on the backend and re-evaluated at finalize.
 * - Finance remains the owner of financial truth (replaceable boundary stub).
 * - No Phase 5 work (section allocation, capacity, admission/registration numbers).
 */
class EnrollmentService
{
    public function start(School $school, User $actor, array $data): Enrollment
    {
        return DB::transaction(function () use ($school, $actor, $data) {
            $sessionId = $data['academic_session_id'] ?? null;
            if (! $sessionId) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session is required.',
                ]);
            }

            $this->assertSessionBelongsToSchool($school, $sessionId);

            $admissionId = $data['admission_id'] ?? null;
            $admission = null;

            if ($admissionId) {
                $admission = Admission::query()
                    ->whereKey($admissionId)
                    ->lockForUpdate()
                    ->first();

                if (! $admission || $admission->school_id !== $school->id) {
                    throw ValidationException::withMessages([
                        'admission_id' => 'Admission does not belong to the current school.',
                    ]);
                }

                if ($admission->status !== Admission::STATUS_ACCEPTED) {
                    throw ValidationException::withMessages([
                        'admission_id' => 'Only accepted admissions can start enrollment.',
                    ]);
                }

                $existing = Enrollment::query()
                    ->where('admission_id', $admission->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'admission_id' => 'An enrollment already exists for this admission.',
                    ]);
                }

                if ($admission->academic_session_id && $admission->academic_session_id !== $sessionId) {
                    throw ValidationException::withMessages([
                        'academic_session_id' => 'Enrollment session must match the admission session.',
                    ]);
                }
            }

            $enrollment = new Enrollment();
            $enrollment->school_id = $school->id;
            $enrollment->student_id = null;
            $enrollment->academic_session_id = $sessionId;
            $enrollment->admission_id = $admission?->id;
            $enrollment->status = Enrollment::STATUS_IN_PROGRESS;
            $enrollment->started_at = now();
            $enrollment->notes = $data['notes'] ?? null;

            $meta = $data['meta'] ?? [];
            if (! empty($data['biodata']) && is_array($data['biodata'])) {
                $meta['biodata'] = $this->sanitizeBiodata($data['biodata']);
            }
            if (! empty($data['profile_id'])) {
                $meta['profile_id'] = $data['profile_id'];
            }
            $meta['source'] = $admission ? 'admission' : ($data['source'] ?? 'direct');
            if ($admission) {
                $meta['admission_id'] = $admission->id;
            }
            $meta['started_by'] = $actor->id;
            $enrollment->meta = $meta;
            $enrollment->save();

            $this->materializeRequirementInstances($enrollment);

            activity()
                ->performedOn($enrollment)
                ->causedBy($actor)
                ->withProperties(['admission_id' => $admission?->id, 'source' => $meta['source'] ?? null])
                ->log('enrollment.started');

            Log::info('Enrollment started', [
                'enrollment_id' => $enrollment->id,
                'school_id' => $school->id,
                'admission_id' => $admission?->id,
                'actor_id' => $actor->id,
            ]);

            return $enrollment->fresh(['requirementInstances.definition', 'admission', 'academicSession']);
        });
    }

    public function updateBiodata(Enrollment $enrollment, User $actor, array $biodata): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $actor, $biodata) {
            $locked = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isIncomplete()) {
                throw ValidationException::withMessages([
                    'status' => 'Biodata can only be updated while enrollment is incomplete.',
                ]);
            }

            $meta = $locked->meta ?? [];
            $sanitized = $this->sanitizeBiodata($biodata);
            $meta['biodata'] = array_merge($meta['biodata'] ?? [], $sanitized);

            if (array_key_exists('profile_id', $biodata)) {
                $profileId = $biodata['profile_id'];
                if ($profileId === null || $profileId === '') {
                    unset($meta['profile_id']);
                } else {
                    if (! Profile::query()->whereKey($profileId)->exists()) {
                        throw ValidationException::withMessages([
                            'profile_id' => 'The specified profile does not exist.',
                        ]);
                    }
                    $meta['profile_id'] = $profileId;
                }
            }

            $meta['biodata_updated_at'] = now()->toIso8601String();
            $meta['biodata_updated_by'] = $actor->id;
            $locked->meta = $meta;
            $locked->save();

            activity()->performedOn($locked)->causedBy($actor)->log('enrollment.biodata_updated');

            return $locked->fresh(['requirementInstances.definition']);
        });
    }

    public function materializeRequirementInstances(Enrollment $enrollment): void
    {
        $definitions = EnrollmentRequirementDefinition::query()
            ->where('school_id', $enrollment->school_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($definitions as $definition) {
            EnrollmentRequirementInstance::query()->firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'definition_id' => $definition->id,
                ],
                ['status' => EnrollmentRequirementInstance::STATUS_PENDING]
            );
        }
    }

    public function satisfyRequirement(
        Enrollment $enrollment,
        EnrollmentRequirementInstance $instance,
        User $actor,
        array $data = []
    ): EnrollmentRequirementInstance {
        return DB::transaction(function () use ($enrollment, $instance, $actor, $data) {
            $lockedEnrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if (! $lockedEnrollment->isIncomplete()) {
                throw ValidationException::withMessages([
                    'status' => 'Requirements can only be updated while enrollment is incomplete.',
                ]);
            }

            if ($instance->enrollment_id !== $lockedEnrollment->id) {
                throw ValidationException::withMessages([
                    'instance' => 'Requirement instance does not belong to this enrollment.',
                ]);
            }

            $locked = EnrollmentRequirementInstance::query()
                ->whereKey($instance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->status = EnrollmentRequirementInstance::STATUS_SATISFIED;
            $locked->satisfied_at = now();
            $locked->satisfied_by = $actor->id;
            $locked->waived_at = null;
            $locked->waived_by = null;
            $locked->waiver_reason = null;

            if (isset($data['document_id'])) {
                $locked->document_id = $data['document_id'];
            }
            if (isset($data['external_reference'])) {
                $locked->external_reference = $data['external_reference'];
            }
            if (isset($data['meta']) && is_array($data['meta'])) {
                $locked->meta = array_merge($locked->meta ?? [], $data['meta']);
            }

            $locked->save();

            activity()
                ->performedOn($lockedEnrollment)
                ->causedBy($actor)
                ->withProperties(['instance_id' => $locked->id, 'definition_id' => $locked->definition_id])
                ->log('enrollment.requirement_satisfied');

            return $locked->fresh('definition');
        });
    }

    public function waiveRequirement(
        Enrollment $enrollment,
        EnrollmentRequirementInstance $instance,
        User $actor,
        string $reason
    ): EnrollmentRequirementInstance {
        return DB::transaction(function () use ($enrollment, $instance, $actor, $reason) {
            $lockedEnrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if (! $lockedEnrollment->isIncomplete()) {
                throw ValidationException::withMessages([
                    'status' => 'Requirements can only be updated while enrollment is incomplete.',
                ]);
            }

            if ($instance->enrollment_id !== $lockedEnrollment->id) {
                throw ValidationException::withMessages([
                    'instance' => 'Requirement instance does not belong to this enrollment.',
                ]);
            }

            if (trim($reason) === '') {
                throw ValidationException::withMessages([
                    'waiver_reason' => 'A waiver reason is required.',
                ]);
            }

            $locked = EnrollmentRequirementInstance::query()
                ->whereKey($instance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->status = EnrollmentRequirementInstance::STATUS_WAIVED;
            $locked->waived_at = now();
            $locked->waived_by = $actor->id;
            $locked->waiver_reason = $reason;
            $locked->save();

            activity()
                ->performedOn($lockedEnrollment)
                ->causedBy($actor)
                ->withProperties([
                    'instance_id' => $locked->id,
                    'definition_id' => $locked->definition_id,
                    'reason' => $reason,
                ])
                ->log('enrollment.requirement_waived');

            return $locked->fresh('definition');
        });
    }

    /** @return array{ready: bool, blockers: array<int, string>, details: array} */
    public function evaluateReadiness(Enrollment $enrollment): array
    {
        $blockers = [];
        $details = [
            'biodata' => null,
            'identity' => null,
            'requirements' => [],
            'finance' => null,
            'duplicate_active' => null,
        ];

        $biodata = $enrollment->meta['biodata'] ?? [];
        $missingBiodata = [];
        foreach (['first_name', 'last_name'] as $field) {
            if (empty($biodata[$field])) {
                $missingBiodata[] = $field;
            }
        }
        if ($missingBiodata) {
            $blockers[] = 'Missing required biodata: '.implode(', ', $missingBiodata);
        }
        $details['biodata'] = ['present' => $biodata, 'missing' => $missingBiodata];

        $identity = $this->evaluateIdentityResolution($enrollment);
        $details['identity'] = $identity;
        if (! $identity['resolvable']) {
            $blockers[] = $identity['message'];
        }

        $instances = $enrollment->relationLoaded('requirementInstances')
            ? $enrollment->requirementInstances
            : $enrollment->requirementInstances()->with('definition')->get();

        foreach ($instances as $instance) {
            $definition = $instance->definition;
            $isRequired = $definition?->is_required ?? true;
            $complete = $instance->isComplete();

            $details['requirements'][] = [
                'instance_id' => $instance->id,
                'definition_id' => $instance->definition_id,
                'code' => $definition?->code,
                'name' => $definition?->name,
                'type' => $definition?->type,
                'is_required' => $isRequired,
                'status' => $instance->status,
                'complete' => $complete,
            ];

            if ($isRequired && ! $complete) {
                $blockers[] = 'Required requirement pending: '.($definition?->name ?? $instance->definition_id);
            }
        }

        $financeOk = $this->financePrerequisiteSatisfied($enrollment);
        $details['finance'] = ['satisfied' => $financeOk];
        if (! $financeOk) {
            $blockers[] = 'Financial prerequisite not satisfied.';
        }

        return ['ready' => count($blockers) === 0, 'blockers' => $blockers, 'details' => $details];
    }

    public function finalize(Enrollment $enrollment, User $actor): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $actor) {
            $locked = Enrollment::query()
                ->whereKey($enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isFinalizable()) {
                throw ValidationException::withMessages([
                    'status' => 'Enrollment cannot be finalized in its current state.',
                ]);
            }

            if ($locked->student_id) {
                throw ValidationException::withMessages([
                    'student_id' => 'Enrollment already has a linked student.',
                ]);
            }

            $this->materializeRequirementInstances($locked);
            $locked->load('requirementInstances.definition');

            $readiness = $this->evaluateReadiness($locked);
            if (! $readiness['ready']) {
                throw ValidationException::withMessages([
                    'readiness' => $readiness['blockers'],
                ]);
            }

            $biodata = $locked->meta['biodata'] ?? [];
            $school = School::query()->whereKey($locked->school_id)->firstOrFail();

            $profile = $this->resolveProfile($locked, $biodata, $actor);
            $this->applyBiodataToProfile($profile, $biodata);
            $profile->save();

            $student = $this->resolveOrCreateStudent($school, $profile, $biodata, $locked);

            if ($locked->admission_id) {
                $other = Enrollment::query()
                    ->where('admission_id', $locked->admission_id)
                    ->where('id', '!=', $locked->id)
                    ->lockForUpdate()
                    ->exists();
                if ($other) {
                    throw ValidationException::withMessages([
                        'admission_id' => 'Another enrollment already exists for this admission.',
                    ]);
                }

                $admission = Admission::query()->whereKey($locked->admission_id)->lockForUpdate()->first();
                if ($admission && ! $admission->student_id) {
                    $admission->student_id = $student->id;
                    $admission->save();
                }
            }

            $locked->student_id = $student->id;
            $locked->status = Enrollment::STATUS_ACTIVE;
            $locked->activated_at = now();
            $meta = $locked->meta ?? [];
            $meta['finalized_at'] = now()->toIso8601String();
            $meta['finalized_by'] = $actor->id;
            $meta['profile_id'] = $profile->id;
            $locked->meta = $meta;
            $locked->save();

            activity()
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties(['student_id' => $student->id, 'profile_id' => $profile->id])
                ->log('enrollment.finalized');

            Log::info('Enrollment finalized', [
                'enrollment_id' => $locked->id,
                'student_id' => $student->id,
                'profile_id' => $profile->id,
                'school_id' => $school->id,
                'actor_id' => $actor->id,
            ]);

            return $locked->fresh(['student.profile', 'admission', 'requirementInstances.definition', 'academicSession']);
        });
    }

    public function financePrerequisiteSatisfied(Enrollment $enrollment): bool
    {
        $instances = $enrollment->relationLoaded('requirementInstances')
            ? $enrollment->requirementInstances
            : $enrollment->requirementInstances()->with('definition')->get();

        foreach ($instances as $instance) {
            $definition = $instance->definition;
            if (! $definition) {
                continue;
            }
            if ($definition->type === EnrollmentRequirementDefinition::TYPE_PAYMENT
                && ($definition->is_required ?? true)
                && ! $instance->isComplete()
            ) {
                return false;
            }
        }

        return true;
    }

    /** @return array{resolvable: bool, strategy: string|null, message: string|null, profile_id: string|null} */
    protected function evaluateIdentityResolution(Enrollment $enrollment): array
    {
        $meta = $enrollment->meta ?? [];
        $biodata = $meta['biodata'] ?? [];
        $explicitProfileId = $meta['profile_id'] ?? null;

        if ($explicitProfileId) {
            $exists = Profile::query()->whereKey($explicitProfileId)->exists();
            if (! $exists) {
                return [
                    'resolvable' => false,
                    'strategy' => 'explicit',
                    'message' => 'Linked profile_id does not exist. Resolve identity before finalizing.',
                    'profile_id' => null,
                ];
            }

            return [
                'resolvable' => true,
                'strategy' => 'explicit',
                'message' => null,
                'profile_id' => $explicitProfileId,
            ];
        }

        $email = isset($biodata['email']) ? strtolower(trim((string) $biodata['email'])) : null;
        if ($email) {
            return [
                'resolvable' => true,
                'strategy' => 'email',
                'message' => null,
                'profile_id' => null,
            ];
        }

        return [
            'resolvable' => false,
            'strategy' => null,
            'message' => 'Identity cannot be safely resolved without an email or an explicitly linked profile_id. Staff must resolve the person before finalizing.',
            'profile_id' => null,
        ];
    }

    protected function resolveProfile(Enrollment $enrollment, array $biodata, User $actor): Profile
    {
        $meta = $enrollment->meta ?? [];
        $explicitProfileId = $meta['profile_id'] ?? null;

        if ($explicitProfileId) {
            $profile = Profile::query()->whereKey($explicitProfileId)->lockForUpdate()->first();
            if (! $profile) {
                throw ValidationException::withMessages([
                    'profile_id' => 'The specified profile does not exist.',
                ]);
            }

            return $profile;
        }

        $email = isset($biodata['email']) ? strtolower(trim((string) $biodata['email'])) : null;
        if ($email) {
            $existing = Profile::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            if ($existing->count() > 1) {
                throw ValidationException::withMessages([
                    'identity' => 'Multiple profiles share this email. Staff must supply an explicit profile_id.',
                ]);
            }

            if ($existing->count() === 1) {
                return $existing->first();
            }

            $profile = new Profile();
            $this->applyBiodataToProfile($profile, $biodata);
            if (! $profile->email) {
                $profile->email = $email;
            }
            $profile->save();

            activity()
                ->performedOn($profile)
                ->causedBy($actor)
                ->log('profile.created_via_enrollment_finalize');

            return $profile;
        }

        throw ValidationException::withMessages([
            'identity' => 'Identity cannot be safely resolved without an email or an explicitly linked profile_id.',
        ]);
    }

    protected function applyBiodataToProfile(Profile $profile, array $biodata): void
    {
        $map = [
            'first_name' => 'first_name',
            'middle_name' => 'middle_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'phone' => 'phone',
            'date_of_birth' => 'date_of_birth',
            'gender' => 'gender',
            'title' => 'title',
        ];

        foreach ($map as $from => $to) {
            if (! array_key_exists($from, $biodata) || $biodata[$from] === null || $biodata[$from] === '') {
                continue;
            }
            $value = is_string($biodata[$from]) ? trim($biodata[$from]) : $biodata[$from];
            if ($from === 'email') {
                $value = strtolower((string) $value);
            }
            $profile->setAttribute($to, $value);
        }

        if (empty($profile->first_name) && ! empty($biodata['first_name'])) {
            $profile->first_name = trim((string) $biodata['first_name']);
        }
        if (empty($profile->last_name) && ! empty($biodata['last_name'])) {
            $profile->last_name = trim((string) $biodata['last_name']);
        }
    }

    protected function resolveOrCreateStudent(
        School $school,
        Profile $profile,
        array $biodata,
        Enrollment $lockedEnrollment
    ): Student {
        $existing = Student::withTrashed()
            ->where('school_id', $school->id)
            ->where('profile_id', $profile->id)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $activeConflict = Enrollment::query()
                ->where('school_id', $school->id)
                ->where('academic_session_id', $lockedEnrollment->academic_session_id)
                ->where('student_id', $existing->id)
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->where('id', '!=', $lockedEnrollment->id)
                ->lockForUpdate()
                ->exists();

            if ($activeConflict) {
                throw ValidationException::withMessages([
                    'student_id' => 'An active enrollment already exists for this student in the selected session.',
                ]);
            }

            if ($this->studentHasColumn('status') && method_exists($existing, 'isActive') && ! $existing->isActive()) {
                $existing->setAttribute('status', 'active');
                $existing->save();
            }

            return $existing;
        }

        try {
            $student = new Student();
            $student->school_id = $school->id;
            $student->profile_id = $profile->id;
            if ($this->studentHasColumn('status')) {
                $student->setAttribute('status', 'active');
            }
            $student->save();

            return $student;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                $winner = Student::withTrashed()
                    ->where('school_id', $school->id)
                    ->where('profile_id', $profile->id)
                    ->lockForUpdate()
                    ->first();

                if ($winner) {
                    if ($winner->trashed()) {
                        $winner->restore();
                    }

                    return $winner;
                }
            }

            throw $e;
        }
    }

    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());

        return in_array($code, ['1062', '23505'], true)
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }

    protected function sanitizeBiodata(array $biodata): array
    {
        $allowed = [
            'first_name', 'last_name', 'middle_name', 'email', 'phone',
            'date_of_birth', 'gender', 'nationality', 'title',
            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
            'profile_id',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $biodata) || $biodata[$key] === null || $biodata[$key] === '') {
                continue;
            }
            $clean[$key] = is_string($biodata[$key]) ? trim($biodata[$key]) : $biodata[$key];
        }

        if (isset($clean['email'])) {
            $clean['email'] = strtolower((string) $clean['email']);
        }

        return $clean;
    }

    protected function assertSessionBelongsToSchool(School $school, string $sessionId): void
    {
        $ok = AcademicSession::query()
            ->whereKey($sessionId)
            ->where('school_id', $school->id)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session does not belong to the current school.',
            ]);
        }
    }

    protected function studentHasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('students', $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
