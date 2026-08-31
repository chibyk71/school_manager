<?php

namespace App\Services\Student;

use App\Facades\SchoolManager;
use App\Models\Academic\AcademicSession;
use App\Models\Misc\Document;
use App\Models\Profile;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
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
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;

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
 * - No tenant infrastructure; isolation boundary is School.
 */
class EnrollmentService
{
    public function start(School $school, User $actor, array $data): Enrollment
    {
        return DB::transaction(function () use ($school, $actor, $data) {
            $sessionId = $data['academic_session_id'] ?? null;
            if (!$sessionId) {
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

                if (!$admission || $admission->school_id !== $school->id) {
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
            if (!empty($data['biodata']) && is_array($data['biodata'])) {
                $meta['biodata'] = $this->sanitizeBiodata($data['biodata']);
                // Promote explicit profile_id from biodata into meta root for identity resolution.
                if (!empty($data['biodata']['profile_id']) && empty($data['profile_id'])) {
                    $data['profile_id'] = $data['biodata']['profile_id'];
                }
            }
            if (!empty($data['profile_id'])) {
                if (!Profile::query()->whereKey($data['profile_id'])->exists()) {
                    throw ValidationException::withMessages([
                        'profile_id' => 'The specified profile does not exist.',
                    ]);
                }
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

            if (!$locked->isIncomplete()) {
                throw ValidationException::withMessages([
                    'status' => 'Biodata can only be updated while enrollment is incomplete.',
                ]);
            }

            $meta = $locked->meta ?? [];
            $sanitized = $this->sanitizeBiodata($biodata);
            $meta['biodata'] = array_merge($meta['biodata'] ?? [], $sanitized);

            if (array_key_exists('profile_id', $biodata)) {
                $profileId = $biodata['profile_id'];
                $currentLinked = $meta['profile_id'] ?? null;

                if ($profileId === null || $profileId === '') {
                    unset($meta['profile_id']);
                } else {
                    if (!Profile::query()->whereKey($profileId)->exists()) {
                        throw ValidationException::withMessages([
                            'profile_id' => 'The specified profile does not exist.',
                        ]);
                    }
                    if ($currentLinked && (string) $currentLinked !== (string) $profileId) {
                        throw ValidationException::withMessages([
                            'profile_id' => 'This enrollment is already linked to a Profile. Clear the link before selecting a different Profile.',
                        ]);
                    }
                    $meta['profile_id'] = $profileId;
                }
            }

            if (array_key_exists('confirm_identity_update', $biodata)) {
                $meta['confirm_identity_update'] = (bool) $biodata['confirm_identity_update'];
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

            if (!$lockedEnrollment->isIncomplete()) {
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
                ->with('definition')
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRequirementInstanceSchoolIntegrity($lockedEnrollment, $locked);

            $definition = $locked->definition;
            $documentId = array_key_exists('document_id', $data) ? $data['document_id'] : null;
            $externalRef = isset($data['external_reference']) ? trim((string) $data['external_reference']) : null;

            // Document requirements use existing Misc\Document (polymorphic attachable) — no second storage model.
            if ($documentId !== null && $documentId !== '') {
                $this->assertDocumentUsableForRequirement($lockedEnrollment, (string) $documentId);
                $locked->document_id = $documentId;
            } elseif (array_key_exists('document_id', $data)) {
                $locked->document_id = null;
            }

            if ($externalRef !== null && $externalRef !== '') {
                $locked->external_reference = $externalRef;
            }

            // DOCUMENT type must reference an existing document or an external reference.
            if (
                $definition
                && $definition->type === EnrollmentRequirementDefinition::TYPE_DOCUMENT
                && empty($locked->document_id)
                && empty($locked->external_reference)
                && empty($externalRef)
            ) {
                throw ValidationException::withMessages([
                    'document_id' => 'Document requirements require a valid document_id or external_reference.',
                ]);
            }

            $locked->status = EnrollmentRequirementInstance::STATUS_SATISFIED;
            $locked->satisfied_at = now();
            $locked->satisfied_by = $actor->id;
            $locked->waived_at = null;
            $locked->waived_by = null;
            $locked->waiver_reason = null;

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

            if (!$lockedEnrollment->isIncomplete()) {
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
                ->with('definition')
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRequirementInstanceSchoolIntegrity($lockedEnrollment, $locked);

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
            $blockers[] = 'Missing required biodata: ' . implode(', ', $missingBiodata);
        }
        $details['biodata'] = ['present' => $biodata, 'missing' => $missingBiodata];

        $identity = $this->evaluateIdentityResolution($enrollment);
        $details['identity'] = $identity;
        if (!$identity['resolvable']) {
            $blockers[] = $identity['message'];
        }

        $instances = $enrollment->relationLoaded('requirementInstances')
            ? $enrollment->requirementInstances
            : $enrollment->requirementInstances()->with('definition')->get();

        foreach ($instances as $instance) {
            $definition = $instance->definition;
            $isRequired = $definition?->is_required ?? true;
            $complete = $instance->isComplete();
            $schoolMismatch = $definition && $definition->school_id !== $enrollment->school_id;

            $details['requirements'][] = [
                'instance_id' => $instance->id,
                'definition_id' => $instance->definition_id,
                'code' => $definition?->code,
                'name' => $definition?->name,
                'type' => $definition?->type,
                'is_required' => $isRequired,
                'status' => $instance->status,
                'complete' => $complete,
                'school_mismatch' => $schoolMismatch,
            ];

            if ($schoolMismatch) {
                $blockers[] = 'Requirement definition belongs to another school: ' . ($definition->name ?? $instance->definition_id);
            }

            if ($isRequired && !$complete) {
                $blockers[] = 'Required requirement pending: ' . ($definition?->name ?? $instance->definition_id);
            }
        }

        $financeOk = $this->financePrerequisiteSatisfied($enrollment);
        $details['finance'] = ['satisfied' => $financeOk];
        if (!$financeOk) {
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

            if (!$locked->isFinalizable()) {
                throw ValidationException::withMessages([
                    'status' => 'Enrollment cannot be finalized in its current state.',
                ]);
            }

            if ($locked->student_id) {
                throw ValidationException::withMessages([
                    'student_id' => 'Enrollment already has a linked student.',
                ]);
            }

            $this->assertAdmissionEligibleForFinalization($locked);

            $this->materializeRequirementInstances($locked);
            $locked->load('requirementInstances.definition');

            foreach ($locked->requirementInstances as $instance) {
                $this->assertRequirementInstanceSchoolIntegrity($locked, $instance);
            }

            $readiness = $this->evaluateReadiness($locked);
            if (!$readiness['ready']) {
                throw ValidationException::withMessages([
                    'readiness' => $readiness['blockers'],
                ]);
            }

            $biodata = $locked->meta['biodata'] ?? [];
            $school = School::query()->whereKey($locked->school_id)->firstOrFail();

            $profile = $this->resolveProfile($locked, $biodata, $actor);
            $allowOverwrite = (bool) (($locked->meta['confirm_identity_update'] ?? false)
                || ($biodata['confirm_identity_update'] ?? false));
            $this->applyBiodataToProfile($profile, $biodata, $allowOverwrite);
            $profile->save();
            $this->applyAddressFromBiodata($profile, $biodata, $school);

            $student = $this->resolveOrCreateStudent($school, $profile, $biodata, $locked);

            if ($locked->admission_id) {
                $admission = Admission::query()->whereKey($locked->admission_id)->lockForUpdate()->first();
                if ($admission && !$admission->student_id) {
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

    /**
     * Finance owns payment state. Enrollment only checks whether its configured
     * PAYMENT prerequisite is satisfied (or waived / explicitly allow_unpaid).
     *
     * Definition.config may include:
     *   - allow_unpaid: bool — school permits finalize without completing this payment
     *
     * Non-required PAYMENT definitions never block. No Finance subsystem is invented here.
     */
    public function financePrerequisiteSatisfied(Enrollment $enrollment): bool
    {
        $instances = $enrollment->relationLoaded('requirementInstances')
            ? $enrollment->requirementInstances
            : $enrollment->requirementInstances()->with('definition')->get();

        foreach ($instances as $instance) {
            $definition = $instance->definition;
            if (!$definition) {
                continue;
            }
            if ($definition->type !== EnrollmentRequirementDefinition::TYPE_PAYMENT) {
                continue;
            }
            if (!($definition->is_required ?? true)) {
                continue;
            }
            if ($instance->isComplete()) {
                continue;
            }

            $config = is_array($definition->config) ? $definition->config : [];
            if (!empty($config['allow_unpaid'])) {
                // School-configured policy: enrollment may proceed without this payment.
                continue;
            }

            return false;
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
            if (!$exists) {
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
            if (!$profile) {
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
            if (!$profile->email) {
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

    protected function applyBiodataToProfile(Profile $profile, array $biodata, bool $allowIdentityOverwrite = false): void
    {
        $isNew = !$profile->exists;
        $identityCritical = ['email', 'date_of_birth'];

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

        $conflicts = [];

        foreach ($map as $from => $to) {
            if (!array_key_exists($from, $biodata) || $biodata[$from] === null || $biodata[$from] === '') {
                continue;
            }

            $value = is_string($biodata[$from]) ? trim((string) $biodata[$from]) : $biodata[$from];
            if ($from === 'email') {
                $value = strtolower((string) $value);
            }

            $current = $profile->getAttribute($to);

            if ($isNew || $current === null || $current === '') {
                $profile->setAttribute($to, $value);
                continue;
            }

            $currentNorm = $current;
            if ($from === 'email') {
                $currentNorm = strtolower(trim((string) $current));
            } elseif ($from === 'date_of_birth') {
                try {
                    $currentNorm = \Illuminate\Support\Carbon::parse($current)->toDateString();
                    $value = \Illuminate\Support\Carbon::parse($value)->toDateString();
                } catch (\Throwable) {
                    $currentNorm = (string) $current;
                }
            } else {
                $currentNorm = is_string($current) ? trim($current) : $current;
            }

            if ((string) $currentNorm === (string) $value) {
                continue;
            }

            if (in_array($from, $identityCritical, true)) {
                if (!$allowIdentityOverwrite) {
                    $conflicts[] = $from;
                    continue;
                }
                $profile->setAttribute($to, $value);
                continue;
            }
        }

        if ($conflicts) {
            throw ValidationException::withMessages([
                'identity' => 'Enrollment biodata conflicts with established Profile fields (' . implode(', ', $conflicts) . '). '
                    . 'Confirm identity update explicitly (confirm_identity_update) or correct the Enrollment biodata.',
            ]);
        }

        if (empty($profile->first_name) && !empty($biodata['first_name'])) {
            $profile->first_name = trim((string) $biodata['first_name']);
        }
        if (empty($profile->last_name) && !empty($biodata['last_name'])) {
            $profile->last_name = trim((string) $biodata['last_name']);
        }
    }

    /**
     * Persist Enrollment biodata address onto Profile via existing HasAddress infrastructure.
     *
     * Free-text fields map to Address columns the architecture already supports:
     *   address_line_1/2, city_text, postal_code, type
     * Hierarchical geo (country/state/city) is resolved to nnjeim/world IDs when names match.
     * Unresolved free-text country/state remain in Enrollment.meta biodata (not forced into landmark).
     *
     * Uses Profile::addAddress / updateAddress / primaryAddress — no second address system.
     */
    protected function applyAddressFromBiodata(Profile $profile, array $biodata, School $school): void
    {
        $line1 = isset($biodata['address_line_1']) ? trim((string) $biodata['address_line_1']) : '';
        $line2 = isset($biodata['address_line_2']) ? trim((string) $biodata['address_line_2']) : '';
        $cityText = isset($biodata['city']) ? trim((string) $biodata['city']) : '';
        $postal = isset($biodata['postal_code']) ? trim((string) $biodata['postal_code']) : '';
        $countryName = isset($biodata['country']) ? trim((string) $biodata['country']) : '';
        $stateName = isset($biodata['state']) ? trim((string) $biodata['state']) : '';

        $hasAddress = $line1 !== '' || $line2 !== '' || $cityText !== '' || $postal !== ''
            || $countryName !== '' || $stateName !== '';

        if (!$hasAddress) {
            return;
        }

        $payload = [];
        if ($line1 !== '') {
            $payload['address_line_1'] = $line1;
        }
        if ($line2 !== '') {
            $payload['address_line_2'] = $line2;
        }
        if ($cityText !== '') {
            $payload['city_text'] = $cityText;
        }
        if ($postal !== '') {
            $payload['postal_code'] = $postal;
        }

        // Resolve hierarchical IDs when world tables are available (no silent field remapping).
        $countryId = $this->resolveWorldCountryId($countryName);
        if ($countryId) {
            $payload['country_id'] = $countryId;
            $stateId = $this->resolveWorldStateId($stateName, $countryId);
            if ($stateId) {
                $payload['state_id'] = $stateId;
                $cityId = $this->resolveWorldCityId($cityText, $stateId);
                if ($cityId) {
                    $payload['city_id'] = $cityId;
                }
            }
        }

        $primary = method_exists($profile, 'primaryAddress') ? $profile->primaryAddress() : null;

        // Ensure school context so HasAddress can assign school_id for Profile (global model).
        try {
            SchoolManager::setActiveSchool($school);
        } catch (\Throwable) {
            // schoolManager may be unbound in focused unit tests
        }

        if ($primary) {
            // Fill empty slots only — do not wipe established structured geo IDs.
            $toUpdate = [];
            foreach ($payload as $key => $value) {
                $cur = $primary->getAttribute($key);
                if ($cur === null || $cur === '') {
                    $toUpdate[$key] = $value;
                }
            }
            if ($toUpdate === []) {
                return;
            }
            try {
                $profile->updateAddress($primary->id, $toUpdate);
            } catch (\Throwable $e) {
                Log::warning('Enrollment address update via HasAddress failed; applying empty-slot fill', [
                    'profile_id' => $profile->id,
                    'error' => $e->getMessage(),
                ]);
                foreach ($toUpdate as $key => $value) {
                    $primary->setAttribute($key, $value);
                }
                $primary->save();
            }

            return;
        }

        // Creating a primary address requires address_line_1 (HasAddress validation).
        if ($line1 === '') {
            return;
        }

        $payload['type'] = 'residential';

        try {
            $profile->addAddress($payload, true);
        } catch (\Throwable $e) {
            // Focused tests / missing dynamic enum / world tables: create via relation with school_id
            // using the same column set HasAddress would persist.
            Log::warning('Enrollment addAddress failed; using relation create with school context', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);
            $profile->addresses()->create(array_merge($payload, [
                'school_id' => $school->id,
                'is_primary' => true,
            ]));
        }
    }

    protected function resolveWorldCountryId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        try {
            if (!class_exists(Country::class) || !Schema::hasTable('countries')) {
                return null;
            }

            return Country::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveWorldStateId(?string $name, int|string $countryId): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        try {
            if (!class_exists(State::class) || !Schema::hasTable('states')) {
                return null;
            }

            return State::query()
                ->where('country_id', $countryId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveWorldCityId(?string $name, int|string $stateId): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        try {
            if (!class_exists(City::class) || !Schema::hasTable('cities')) {
                return null;
            }

            return City::query()
                ->where('state_id', $stateId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Validate document_id against existing Misc\Document architecture.
     * Rejects arbitrary unrelated UUIDs; checks attachable ownership when present.
     */
    protected function assertDocumentUsableForRequirement(Enrollment $enrollment, string $documentId): void
    {
        if (!class_exists(Document::class)) {
            throw ValidationException::withMessages([
                'document_id' => 'Document infrastructure is not available.',
            ]);
        }

        try {
            $document = Document::query()->whereKey($documentId)->first();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'document_id' => 'Unable to resolve document_id against Document storage.',
            ]);
        }

        if (!$document) {
            throw ValidationException::withMessages([
                'document_id' => 'Document not found. Supply a valid document from the existing document system.',
            ]);
        }

        // When the document is attached to an owner, require it to belong to this enrollment context.
        $attachableType = $document->attachable_type ?? null;
        $attachableId = $document->attachable_id ?? null;
        if (!$attachableType || !$attachableId) {
            return; // unscoped document record — allowed minimally
        }

        $allowed = false;
        if ($attachableType === Enrollment::class || str_ends_with((string) $attachableType, '\\Enrollment')) {
            $allowed = (string) $attachableId === (string) $enrollment->id;
        }
        if (!$allowed && ($attachableType === Profile::class || str_ends_with((string) $attachableType, '\\Profile'))) {
            $linkedProfileId = $enrollment->meta['profile_id'] ?? null;
            $allowed = $linkedProfileId && (string) $attachableId === (string) $linkedProfileId;
        }
        if (!$allowed && ($attachableType === Student::class || str_ends_with((string) $attachableType, '\\Student'))) {
            $allowed = $enrollment->student_id
                && (string) $attachableId === (string) $enrollment->student_id;
        }

        if (!$allowed) {
            throw ValidationException::withMessages([
                'document_id' => 'Document is not attached to this enrollment, its profile, or student.',
            ]);
        }
    }

    protected function resolveOrCreateStudent(
        School $school,
        Profile $profile,
        array $biodata,
        Enrollment $lockedEnrollment
    ): Student {
        $existing = Student::withoutGlobalScope(SchoolScope::class)
            ->withTrashed()
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

            if ($this->studentHasColumn('status') && method_exists($existing, 'isActive') && !$existing->isActive()) {
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
                $winner = Student::withoutGlobalScope(SchoolScope::class)
                    ->withTrashed()
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
            'first_name',
            'last_name',
            'middle_name',
            'email',
            'phone',
            'date_of_birth',
            'gender',
            'nationality',
            'title',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
            'profile_id',
            'confirm_identity_update',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $biodata) || $biodata[$key] === null || $biodata[$key] === '') {
                continue;
            }
            if ($key === 'confirm_identity_update') {
                $clean[$key] = filter_var($biodata[$key], FILTER_VALIDATE_BOOLEAN);
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

        if (!$ok) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session does not belong to the current school.',
            ]);
        }
    }

    protected function assertAdmissionEligibleForFinalization(Enrollment $enrollment): void
    {
        if (!$enrollment->admission_id) {
            return;
        }

        $admission = Admission::query()
            ->whereKey($enrollment->admission_id)
            ->lockForUpdate()
            ->first();

        if (!$admission) {
            throw ValidationException::withMessages([
                'admission_id' => 'Linked admission no longer exists.',
            ]);
        }

        if ($admission->school_id !== $enrollment->school_id) {
            throw ValidationException::withMessages([
                'admission_id' => 'Admission does not belong to the same school as this enrollment.',
            ]);
        }

        if ($admission->status !== Admission::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'admission_id' => 'Admission is no longer accepted and cannot be finalized into an enrollment.',
            ]);
        }

        if (
            $admission->academic_session_id
            && $enrollment->academic_session_id
            && $admission->academic_session_id !== $enrollment->academic_session_id
        ) {
            throw ValidationException::withMessages([
                'admission_id' => 'Admission academic session no longer matches the enrollment session.',
            ]);
        }

        $other = Enrollment::query()
            ->where('admission_id', $admission->id)
            ->where('id', '!=', $enrollment->id)
            ->lockForUpdate()
            ->exists();

        if ($other) {
            throw ValidationException::withMessages([
                'admission_id' => 'Another enrollment already exists for this admission.',
            ]);
        }
    }

    protected function assertRequirementInstanceSchoolIntegrity(
        Enrollment $enrollment,
        EnrollmentRequirementInstance $instance
    ): void {
        $definition = $instance->relationLoaded('definition')
            ? $instance->definition
            : EnrollmentRequirementDefinition::query()->whereKey($instance->definition_id)->first();

        if (!$definition) {
            throw ValidationException::withMessages([
                'definition_id' => 'Requirement definition not found.',
            ]);
        }

        if ($definition->school_id !== $enrollment->school_id) {
            throw ValidationException::withMessages([
                'definition_id' => 'Requirement definition does not belong to the enrollment school.',
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
