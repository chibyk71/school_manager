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

/**
 * Phase 5 placement allocation.
 *
 * Capacity convention (ClassSection domain):
 *   capacity = 0  → uncapped / not configured (unlimited)
 *   capacity > 0  → hard capacity; override required when occupancy >= capacity
 *
 * Transaction boundary:
 *   allocateForEnrollment() and placeManually() each establish DB::transaction
 *   so section locks and placement mutations are atomic even when called outside
 *   EnrollmentService::finalize(). Nested calls from finalize() use savepoints.
 *
 * Occupancy is measured from active placements (is_current=true, left_at null)
 * belonging to the section — the authoritative source of truth.
 */
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
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($enrollment, $student, $school, $actor, $options) {
            $this->assertEnrollmentSchool($enrollment, $school);
            $this->assertStudentSchool($student, $school);

            $classLevelId = $options['class_level_id']
                ?? $enrollment->admission?->class_level_id
                ?? ($enrollment->meta['class_level_id'] ?? null);

            if (!$classLevelId) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'Target class level is required for placement.',
                ]);
            }

            $this->assertClassLevelBelongsToSchool($classLevelId, $school);

            $requestedSectionId = $options['class_section_id'] ?? null;
            $override = (bool) ($options['capacity_override'] ?? false);

            if ($requestedSectionId) {
                $section = $this->resolveSection($requestedSectionId, $classLevelId, $school);
                $this->assertCapacityOrOverride($section, $override, $actor);
            } else {
                $section = $this->selectSectionWithCapacity($classLevelId, $school);
                $override = false;
            }

            return $this->createPlacement(
                student: $student,
                school: $school,
                enrollment: $enrollment,
                classLevelId: $classLevelId,
                section: $section,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: RegistrationNumberService::REASON_INITIAL,
                academicSessionId: $enrollment->academic_session_id,
                closePrevious: true
            );
        });
    }

    public function placeManually(
        Student $student,
        School $school,
        string $classLevelId,
        string $classSectionId,
        User $actor,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $classLevelId, $classSectionId, $actor, $options) {
            $this->assertStudentSchool($student, $school);
            $this->assertClassLevelBelongsToSchool($classLevelId, $school);

            $section = $this->resolveSection($classSectionId, $classLevelId, $school);
            $override = (bool) ($options['capacity_override'] ?? false);
            $this->assertCapacityOrOverride($section, $override, $actor);

            $sessionId = $options['academic_session_id']
                ?? $student->currentPlacement?->academic_session_id
                ?? null;

            if (!$sessionId) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session is required for placement.',
                ]);
            }

            $enrollment = isset($options['enrollment_id'])
                ? Enrollment::query()->find($options['enrollment_id'])
                : null;

            return $this->createPlacement(
                student: $student,
                school: $school,
                enrollment: $enrollment,
                classLevelId: $classLevelId,
                section: $section,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: RegistrationNumberService::REASON_MANUAL,
                academicSessionId: $sessionId,
                closePrevious: true
            );
        });
    }

    public function activeOccupancy(ClassSection $section, bool $forUpdate = false): int
    {
        $q = StudentSessionPlacement::query()
            ->where('class_section_id', $section->id)
            ->where('is_current', true)
            ->whereNull('left_at');

        if ($forUpdate) {
            $q->lockForUpdate();
        }

        return $q->count();
    }

    public function selectSectionWithCapacity(string $classLevelId, School $school): ClassSection
    {
        $sections = ClassSection::query()
            ->where('class_level_id', $classLevelId)
            ->where('school_id', $school->id)
            ->ordered()
            ->lockForUpdate()
            ->get();

        if ($sections->isEmpty()) {
            throw ValidationException::withMessages([
                'class_section_id' => 'No class sections exist for the target class level.',
            ]);
        }

        foreach ($sections as $section) {
            if ($this->hasAvailableCapacity($section, forUpdate: true)) {
                return $section;
            }
        }

        throw ValidationException::withMessages([
            'class_section_id' => 'All sections for this class level are at capacity. An authorized capacity override with an explicit section is required.',
        ]);
    }

    /**
     * Capacity convention (must match ClassSection domain docs):
     *   capacity <= 0  → unlimited (0 = uncapped / not configured)
     *   capacity > 0   → hard limit; available when occupancy < capacity
     */
    public function hasAvailableCapacity(ClassSection $section, bool $forUpdate = false): bool
    {
        $capacity = (int) ($section->capacity ?? 0);

        // 0 = uncapped / not configured — never "at capacity"
        if ($capacity <= 0) {
            return true;
        }

        return $this->activeOccupancy($section, $forUpdate) < $capacity;
    }

    /**
     * Assign a permanent Admission Number if the student does not yet have one.
     *
     * Serializes on the Student row (SELECT … FOR UPDATE) so concurrent callers
     * cannot both observe NULL and overwrite each other. Re-check after the
     * lock is mandatory — the number is immutable once assigned.
     */
    public function ensureAdmissionNumber(Student $student, School $school): string
    {
        if (!empty($student->admission_number)) {
            return $student->admission_number;
        }

        return DB::transaction(function () use ($student, $school) {
            $locked = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check under lock: another transaction may have assigned already.
            if (!empty($locked->admission_number)) {
                $student->setRawAttributes($locked->getAttributes(), true);
                $student->syncOriginal();

                return $locked->admission_number;
            }

            $number = IdGenerator::generate('admission_number', $school);
            $locked->admission_number = $number;
            if (empty($locked->admission_date)) {
                $locked->admission_date = now()->toDateString();
            }
            $locked->save();

            $student->setRawAttributes($locked->getAttributes(), true);
            $student->syncOriginal();

            return $number;
        });
    }

    protected function assertCapacityOrOverride(
        ClassSection $section,
        bool $override,
        User $actor
    ): void {
        ClassSection::query()->whereKey($section->id)->lockForUpdate()->first();

        if ($this->hasAvailableCapacity($section, forUpdate: true)) {
            return;
        }

        if (!$override) {
            throw ValidationException::withMessages([
                'class_section_id' => 'Selected section is at capacity. Capacity override is required.',
            ]);
        }

        if (!$this->actorMayOverrideCapacity($actor)) {
            throw ValidationException::withMessages([
                'capacity_override' => 'You are not authorized to override section capacity.',
            ]);
        }
    }

    protected function actorMayOverrideCapacity(User $actor): bool
    {
        if (method_exists($actor, 'isAbleTo')) {
            return $actor->isAbleTo('placements.capacity_override')
                || $actor->isAbleTo('enrollments.finalize')
                || $actor->isAbleTo('placements.manage');
        }

        if (method_exists($actor, 'can')) {
            return $actor->can('placements.capacity_override')
                || $actor->can('enrollments.finalize')
                || $actor->can('placements.manage');
        }

        return false;
    }

    protected function createPlacement(
        Student $student,
        School $school,
        ?Enrollment $enrollment,
        string $classLevelId,
        ClassSection $section,
        User $actor,
        bool $capacityOverride,
        ?string $notes,
        string $reason,
        ?string $academicSessionId = null,
        bool $closePrevious = true
    ): StudentSessionPlacement {
        $sessionId = $academicSessionId
            ?? $enrollment?->academic_session_id
            ?? null;

        if (!$sessionId) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session is required for placement.',
            ]);
        }

        if ($closePrevious) {
            $this->closeCurrentPlacementsForSession($student, $sessionId);
        }

        $placement = StudentSessionPlacement::query()->create([
            'student_id' => $student->id,
            'enrollment_id' => $enrollment?->id,
            'academic_session_id' => $sessionId,
            'class_level_id' => $classLevelId,
            'class_section_id' => $section->id,
            'enrolled_at' => now()->toDateString(),
            'left_at' => null,
            'is_current' => true,
            'promotion_outcome' => $reason === RegistrationNumberService::REASON_INITIAL
                ? 'fresh_admission'
                : null,
            'notes' => $notes,
            'capacity_override_used' => $capacityOverride,
            'placed_by' => $actor->id,
            'meta' => [
                'capacity_override' => $capacityOverride,
                'placed_at' => now()->toIso8601String(),
            ],
        ]);

        $regenerate = true;
        if ($reason === RegistrationNumberService::REASON_SECTION_CHANGE) {
            $current = $this->registrationNumbers->currentNumber($student, $school->id);
            if ($current && !$this->registrationNumbers->shouldRegenerateOnSectionChange($school)) {
                $regenerate = false;
                $placement->registration_number = $current;
                $placement->save();
            }
        }

        if ($regenerate) {
            $assignReason = match ($reason) {
                RegistrationNumberService::REASON_MANUAL => RegistrationNumberService::REASON_MANUAL,
                RegistrationNumberService::REASON_SECTION_CHANGE => RegistrationNumberService::REASON_SECTION_CHANGE,
                default => RegistrationNumberService::REASON_INITIAL,
            };
            $number = $this->registrationNumbers->assign(
                $student,
                $school,
                [
                    'academic_session_id' => $sessionId,
                    'class_level_id' => $classLevelId,
                    'class_section_id' => $section->id,
                    'placement_id' => $placement->id,
                    'enrollment_id' => $enrollment?->id,
                ],
                $assignReason,
                $actor
            );
            $placement->registration_number = $number;
            $placement->save();
        }

        $this->syncLegacyPivot($student, $section, $sessionId);

        Log::info('Placement allocated', [
            'student_id' => $student->id,
            'section_id' => $section->id,
            'session_id' => $sessionId,
            'placement_id' => $placement->id,
            'capacity_override' => $capacityOverride,
            'registration_number' => $placement->registration_number,
        ]);

        return $placement->fresh();
    }

    protected function closeCurrentPlacementsForSession(Student $student, string $sessionId): void
    {
        StudentSessionPlacement::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $sessionId)
            ->where('is_current', true)
            ->lockForUpdate()
            ->get()
            ->each(function (StudentSessionPlacement $p) {
                $p->update([
                    'is_current' => false,
                    'left_at' => now()->toDateString(),
                ]);
            });
    }

    protected function syncLegacyPivot(Student $student, ClassSection $section, string $sessionId): void
    {
        if (!Schema::hasTable('student_class_section_pivot')) {
            return;
        }

        DB::table('student_class_section_pivot')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $sessionId)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'left_at' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        $exists = DB::table('student_class_section_pivot')
            ->where('student_id', $student->id)
            ->where('class_section_id', $section->id)
            ->where('academic_session_id', $sessionId)
            ->first();

        if ($exists) {
            DB::table('student_class_section_pivot')
                ->where('id', $exists->id)
                ->update([
                    'is_current' => true,
                    'left_at' => null,
                    'enrolled_at' => now()->toDateString(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('student_class_section_pivot')->insert([
                'student_id' => $student->id,
                'class_section_id' => $section->id,
                'academic_session_id' => $sessionId,
                'is_current' => true,
                'enrolled_at' => now()->toDateString(),
                'left_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function resolveSection(
        string $sectionId,
        string $classLevelId,
        School $school
    ): ClassSection {
        $section = ClassSection::query()
            ->whereKey($sectionId)
            ->lockForUpdate()
            ->first();

        if (!$section) {
            throw ValidationException::withMessages([
                'class_section_id' => 'Class section not found.',
            ]);
        }

        if ($section->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'class_section_id' => 'Class section does not belong to this school.',
            ]);
        }

        if ($section->class_level_id !== $classLevelId) {
            throw ValidationException::withMessages([
                'class_section_id' => 'Class section does not belong to the target class level.',
            ]);
        }

        return $section;
    }

    protected function assertClassLevelBelongsToSchool(string $classLevelId, School $school): void
    {
        $level = ClassLevel::query()->whereKey($classLevelId)->first();
        if (!$level) {
            throw ValidationException::withMessages([
                'class_level_id' => 'Class level not found.',
            ]);
        }

        $ok = false;
        if (isset($level->school_id) && $level->school_id === $school->id) {
            $ok = true;
        } elseif (!empty($level->school_section_id) && Schema::hasTable('school_sections')) {
            $ok = DB::table('school_sections')
                ->where('id', $level->school_section_id)
                ->where('school_id', $school->id)
                ->exists();
        }

        if (!$ok) {
            throw ValidationException::withMessages([
                'class_level_id' => 'Class level does not belong to this school.',
            ]);
        }
    }

    protected function assertEnrollmentSchool(Enrollment $enrollment, School $school): void
    {
        if ($enrollment->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'enrollment' => 'Enrollment does not belong to this school.',
            ]);
        }
    }

    protected function assertStudentSchool(Student $student, School $school): void
    {
        if ($student->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'student' => 'Student does not belong to this school.',
            ]);
        }
    }
}
