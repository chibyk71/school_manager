<?php

namespace App\Services\Student;

use App\Helpers\IdGenerator;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PlacementAllocationService
{
    public function __construct(
        protected RegistrationNumberService $registrationNumbers,
        protected StudentPlacementService $placements
    ) {
    }

    public function allocateForEnrollment(
        Enrollment $enrollment,
        Student $student,
        School $school,
        User $actor,
        ?string $classLevelId = null,
        ?string $classSectionId = null,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($enrollment, $student, $school, $actor, $classLevelId, $classSectionId, $options) {
            $sessionId = $enrollment->academic_session_id
                ?? $options['academic_session_id']
                ?? null;

            if (! $sessionId) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'An academic session is required for placement.',
                ]);
            }

            $this->assertSameSchool($student, $school);
            $this->assertSessionBelongsToSchool($sessionId, $school);

            $levelId = $classLevelId ?? $enrollment->class_level_id ?? $options['class_level_id'] ?? null;
            $sectionId = $classSectionId ?? $enrollment->class_section_id ?? $options['class_section_id'] ?? null;

            if (! $levelId) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'A class level is required for placement.',
                ]);
            }

            $override = (bool) ($options['capacity_override'] ?? false);
            $section = $this->resolveSectionForPlacement(
                $school,
                $sessionId,
                $levelId,
                $sectionId,
                $actor,
                $override
            );

            return $this->createPlacement(
                student: $student,
                school: $school,
                academicSessionId: $sessionId,
                classLevelId: $section->class_level_id,
                classSectionId: $section->id,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: RegistrationNumberService::REASON_INITIAL,
            );
        });
    }

    public function reassignPlacement(
        Student $student,
        School $school,
        string $academicSessionId,
        string $classLevelId,
        ?string $classSectionId,
        User $actor,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $academicSessionId, $classLevelId, $classSectionId, $actor, $options) {
            $this->assertSameSchool($student, $school);
            $this->assertSessionBelongsToSchool($academicSessionId, $school);

            $override = (bool) ($options['capacity_override'] ?? false);
            $section = $this->resolveSectionForPlacement(
                $school,
                $academicSessionId,
                $classLevelId,
                $classSectionId,
                $actor,
                $override
            );

            $existing = StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('school_id', $school->id)
                ->where('academic_session_id', $academicSessionId)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->endPlacement($existing, now(), $options['notes'] ?? 'Reassigned');
            }

            return $this->createPlacement(
                student: $student,
                school: $school,
                academicSessionId: $academicSessionId,
                classLevelId: $section->class_level_id,
                classSectionId: $section->id,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: RegistrationNumberService::REASON_MANUAL,
            );
        });
    }

    /**
     * Phase 6 — section change within the same class level / session.
     * Ends the current active placement and creates a new history row.
     */
    public function changeSection(
        Student $student,
        School $school,
        ClassSection $newSection,
        User $actor,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $newSection, $actor, $options) {
            $this->assertSameSchool($student, $school);
            $this->assertSectionBelongsToSchool($newSection, $school);

            $current = $this->currentActivePlacement($student, $school);
            if (! $current) {
                throw ValidationException::withMessages([
                    'student' => 'Student has no active placement to change section from.',
                ]);
            }

            if ((string) $current->class_section_id === (string) $newSection->id) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Student is already in the selected section.',
                ]);
            }

            if ((string) $current->class_level_id !== (string) $newSection->class_level_id) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Section change must stay within the same class level. Use class change instead.',
                ]);
            }

            $sessionId = $options['academic_session_id'] ?? $current->academic_session_id;
            $this->assertSessionBelongsToSchool($sessionId, $school);

            $override = (bool) ($options['capacity_override'] ?? false);
            $this->assertCapacityAvailable($newSection, $sessionId, $actor, $override);

            $this->endPlacement($current, now(), $options['notes'] ?? 'Section change');

            return $this->createPlacement(
                student: $student,
                school: $school,
                academicSessionId: $sessionId,
                classLevelId: $newSection->class_level_id,
                classSectionId: $newSection->id,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: RegistrationNumberService::REASON_SECTION_CHANGE,
            );
        });
    }

    /**
     * Phase 6 — class (level) change within the same session.
     * Ends current placement; may regenerate registration number per policy.
     */
    public function changeClass(
        Student $student,
        School $school,
        ClassLevel $newLevel,
        ?ClassSection $newSection,
        User $actor,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $newLevel, $newSection, $actor, $options) {
            $this->assertSameSchool($student, $school);
            $this->assertLevelBelongsToSchool($newLevel, $school);

            if ($newSection) {
                $this->assertSectionBelongsToSchool($newSection, $school);
                if ((string) $newSection->class_level_id !== (string) $newLevel->id) {
                    throw ValidationException::withMessages([
                        'class_section_id' => 'Selected section does not belong to the target class level.',
                    ]);
                }
            }

            $current = $this->currentActivePlacement($student, $school);
            if (! $current) {
                throw ValidationException::withMessages([
                    'student' => 'Student has no active placement to change class from.',
                ]);
            }

            if ((string) $current->class_level_id === (string) $newLevel->id) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'Student is already in the selected class level. Use section change instead.',
                ]);
            }

            $sessionId = $options['academic_session_id'] ?? $current->academic_session_id;
            $this->assertSessionBelongsToSchool($sessionId, $school);

            $override = (bool) ($options['capacity_override'] ?? false);
            $section = $this->resolveSectionForPlacement(
                $school,
                $sessionId,
                $newLevel->id,
                $newSection?->id,
                $actor,
                $override
            );

            $this->endPlacement($current, now(), $options['notes'] ?? 'Class change');

            $reason = RegistrationNumberService::REASON_SECTION_CHANGE;
            if ((string) $current->class_level_id !== (string) $section->class_level_id) {
                $reason = RegistrationNumberService::REASON_CLASS_CHANGE;
            }

            return $this->createPlacement(
                student: $student,
                school: $school,
                academicSessionId: $sessionId,
                classLevelId: $section->class_level_id,
                classSectionId: $section->id,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: $reason,
            );
        });
    }

    /**
     * Phase 6 / Promotion — next-session placement with capacity + registration policy.
     * Used by ProcessStudentPromotion for promote and repeat outcomes.
     * Does not own promotion decisions; only materializes placement + regnum policy.
     */
    public function placeForPromotionOutcome(
        Student $student,
        School $school,
        string $nextSessionId,
        string $classLevelId,
        ?string $classSectionId,
        User $actor,
        string $outcome,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $nextSessionId, $classLevelId, $classSectionId, $actor, $outcome, $options) {
            $this->assertSameSchool($student, $school);
            $this->assertSessionBelongsToSchool($nextSessionId, $school);

            $override = (bool) ($options['capacity_override'] ?? false);
            $section = $this->resolveSectionForPlacement(
                $school,
                $nextSessionId,
                $classLevelId,
                $classSectionId,
                $actor,
                $override
            );

            // End any lingering open placement in the *next* session (idempotent re-run).
            $existingNext = StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('school_id', $school->id)
                ->where('academic_session_id', $nextSessionId)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($existingNext) {
                $this->endPlacement($existingNext, now(), $options['notes'] ?? "Replaced by {$outcome}");
            }

            $notes = $options['notes'] ?? null;
            $meta = array_merge($options['meta'] ?? [], [
                'promotion_outcome' => $outcome,
                'capacity_override_used' => $override,
            ]);

            $placement = $this->createPlacement(
                student: $student,
                school: $school,
                academicSessionId: $nextSessionId,
                classLevelId: $section->class_level_id,
                classSectionId: $section->id,
                actor: $actor,
                capacityOverride: $override,
                notes: $notes,
                reason: RegistrationNumberService::REASON_PROMOTION,
                extraMeta: $meta,
                promotionOutcome: $outcome,
            );

            return $placement;
        });
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function currentActivePlacement(Student $student, School $school): ?StudentSessionPlacement
    {
        return StudentSessionPlacement::query()
            ->where('student_id', $student->id)
            ->where('school_id', $school->id)
            ->whereNull('left_at')
            ->orderByDesc('joined_at')
            ->lockForUpdate()
            ->first();
    }

    protected function endPlacement(StudentSessionPlacement $placement, $leftAt, ?string $notes = null): void
    {
        $payload = ['left_at' => $leftAt];
        if ($notes !== null) {
            $existing = $placement->notes;
            $payload['notes'] = $existing ? trim($existing."\n".$notes) : $notes;
        }
        $placement->update($payload);
    }

    /**
     * Active occupancy for a section in a session (for capacity checks).
     * Placements from other sessions must not consume capacity.
     */
    protected function activeOccupancy(ClassSection $section, string $academicSessionId, bool $forUpdate = false): int
    {
        $q = StudentSessionPlacement::query()
            ->where('class_section_id', $section->id)
            ->where('academic_session_id', $academicSessionId)
            ->whereNull('left_at');

        if ($forUpdate) {
            $q->lockForUpdate();
        }

        return $q->count();
    }

    protected function resolveSectionForPlacement(
        School $school,
        string $academicSessionId,
        string $classLevelId,
        ?string $classSectionId,
        User $actor,
        bool $capacityOverride
    ): ClassSection {
        $this->assertLevelBelongsToSchool(
            ClassLevel::query()->findOrFail($classLevelId),
            $school
        );

        if ($classSectionId) {
            $section = ClassSection::query()->lockForUpdate()->findOrFail($classSectionId);
            $this->assertSectionBelongsToSchool($section, $school);
            if ((string) $section->class_level_id !== (string) $classLevelId) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Selected section does not belong to the given class level.',
                ]);
            }
            $this->assertCapacityAvailable($section, $academicSessionId, $actor, $capacityOverride);

            return $section;
        }

        // Auto-pick a section with free capacity under the level.
        $sections = ClassSection::query()
            ->where('class_level_id', $classLevelId)
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->lockForUpdate()
            ->get();

        foreach ($sections as $section) {
            if ($this->hasCapacity($section, $academicSessionId, true)) {
                return $section;
            }
        }

        if ($capacityOverride && $this->actorMayOverrideCapacity($actor)) {
            $first = $sections->first();
            if ($first) {
                return $first;
            }
        }

        throw ValidationException::withMessages([
            'class_section_id' => 'All sections for this class level are at capacity. An authorized capacity override with an explicit section is required.',
        ]);
    }

    protected function hasCapacity(ClassSection $section, string $academicSessionId, bool $forUpdate = false): bool
    {
        $capacity = (int) ($section->capacity ?? 0);
        if ($capacity <= 0) {
            // Unbounded / not configured — treat as available.
            return true;
        }

        return $this->activeOccupancy($section, $academicSessionId, $forUpdate) < $capacity;
    }

    protected function assertCapacityAvailable(
        ClassSection $section,
        string $academicSessionId,
        User $actor,
        bool $override
    ): void {
        if ($this->hasCapacity($section, $academicSessionId, true)) {
            return;
        }

        if ($override && $this->actorMayOverrideCapacity($actor)) {
            return;
        }

        if ($override) {
            throw ValidationException::withMessages([
                'capacity_override' => 'You are not authorized to override section capacity.',
            ]);
        }

        throw ValidationException::withMessages([
            'class_section_id' => 'Selected section is at capacity. Capacity override is required.',
        ]);
    }

    protected function actorMayOverrideCapacity(User $actor): bool
    {
        try {
            if (method_exists($actor, 'isAbleTo')) {
                return $actor->isAbleTo('placements.capacity_override')
                    || $actor->isAbleTo('placements.override_capacity');
            }
            if (method_exists($actor, 'can')) {
                return $actor->can('placements.capacity_override')
                    || $actor->can('placements.override_capacity');
            }
        } catch (\Throwable $e) {
            Log::debug('capacity override auth check failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    protected function createPlacement(
        Student $student,
        School $school,
        string $academicSessionId,
        string $classLevelId,
        string $classSectionId,
        User $actor,
        bool $capacityOverride,
        ?string $notes,
        string $reason,
        array $extraMeta = [],
        ?string $promotionOutcome = null,
    ): StudentSessionPlacement {
        $meta = array_merge([
            'reason' => $reason,
            'capacity_override' => $capacityOverride,
            'actor_id' => $actor->id,
        ], $extraMeta);

        $placement = StudentSessionPlacement::query()->create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'academic_session_id' => $academicSessionId,
            'class_level_id' => $classLevelId,
            'class_section_id' => $classSectionId,
            'joined_at' => now(),
            'left_at' => null,
            'notes' => $notes,
            'promotion_outcome' => $promotionOutcome
                ?? ($reason === RegistrationNumberService::REASON_INITIAL ? null : null),
            'meta' => $meta,
            'capacity_override_used' => $capacityOverride,
            'created_by' => $actor->id,
        ]);

        // Registration number policy
        $shouldAssign = true;
        $regReason = $reason;

        if ($reason === RegistrationNumberService::REASON_SECTION_CHANGE) {
            if (! $this->registrationNumbers->shouldRegenerateOnSectionChange($school)) {
                $shouldAssign = false;
            }
        } elseif ($reason === RegistrationNumberService::REASON_CLASS_CHANGE) {
            if (! $this->registrationNumbers->shouldRegenerateOnClassChange($school)) {
                $shouldAssign = false;
            }
        } elseif ($reason === RegistrationNumberService::REASON_PROMOTION) {
            // Promotion follows regenerate_on_promotion setting (default true via config helper if present).
            $cfg = $this->registrationNumbers->config($school);
            if (! (bool) ($cfg['regenerate_on_promotion'] ?? true)) {
                $shouldAssign = false;
            }
        }

        if ($shouldAssign) {
            $context = [
                'academic_session_id' => $academicSessionId,
                'class_level_id' => $classLevelId,
                'class_section_id' => $classSectionId,
            ];

            $mappedReason = match ($reason) {
                RegistrationNumberService::REASON_MANUAL => RegistrationNumberService::REASON_MANUAL,
                RegistrationNumberService::REASON_SECTION_CHANGE => RegistrationNumberService::REASON_SECTION_CHANGE,
                RegistrationNumberService::REASON_CLASS_CHANGE => RegistrationNumberService::REASON_CLASS_CHANGE,
                RegistrationNumberService::REASON_PROMOTION => RegistrationNumberService::REASON_PROMOTION,
                default => RegistrationNumberService::REASON_INITIAL,
            };

            try {
                $this->registrationNumbers->assign(
                    $student,
                    $school,
                    $context,
                    $mappedReason,
                    $actor
                );
            } catch (\Throwable $e) {
                Log::warning('Registration number assign failed during placement', [
                    'student_id' => $student->id,
                    'reason' => $mappedReason,
                    'error' => $e->getMessage(),
                ]);
                // Placement still stands; regnum can be repaired separately.
            }
        }

        // Keep Student current placement pointer in sync when schema supports it.
        if (Schema::hasColumn('students', 'current_placement_id')) {
            $student->update(['current_placement_id' => $placement->id]);
        }

        return $placement->fresh();
    }

    protected function assertSameSchool(Student $student, School $school): void
    {
        if ((string) $student->school_id !== (string) $school->id) {
            throw ValidationException::withMessages([
                'school' => 'Student does not belong to the given school.',
            ]);
        }
    }

    protected function assertSessionBelongsToSchool(string $sessionId, School $school): void
    {
        $exists = DB::table('academic_sessions')
            ->where('id', $sessionId)
            ->where('school_id', $school->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session does not belong to this school.',
            ]);
        }
    }

    protected function assertSectionBelongsToSchool(ClassSection $section, School $school): void
    {
        if ((string) $section->school_id !== (string) $school->id) {
            throw ValidationException::withMessages([
                'class_section_id' => 'Class section does not belong to this school.',
            ]);
        }
    }

    protected function assertLevelBelongsToSchool(ClassLevel $level, School $school): void
    {
        if ((string) $level->school_id !== (string) $school->id) {
            throw ValidationException::withMessages([
                'class_level_id' => 'Class level does not belong to this school.',
            ]);
        }
    }
}
