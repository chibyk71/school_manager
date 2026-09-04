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
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($enrollment, $student, $school, $actor, $options) {
            $this->assertStudentSchool($student, $school);
            $this->assertEnrollmentContext($enrollment, $school, $student);

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

            $sessionId = $enrollment->academic_session_id;
            if (!$sessionId) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session is required for placement.',
                ]);
            }
            // Enrollment.school_id may match while academic_session belongs to another school.
            $this->assertAcademicSessionBelongsToSchool($sessionId, $school);

            $section = $this->resolveSectionForCapacity(
                $classLevelId,
                $requestedSectionId,
                $school,
                $sessionId,
                $actor,
                $override
            );

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
                academicSessionId: $sessionId,
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

            $sessionId = $options['academic_session_id'] ?? null;
            if (!$sessionId) {
                $current = StudentSessionPlacement::query()
                    ->where('student_id', $student->id)
                    ->where('is_current', true)
                    ->whereNull('left_at')
                    ->first();
                $sessionId = $current?->academic_session_id;
            }
            if (!$sessionId) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session is required for manual placement.',
                ]);
            }
            $this->assertAcademicSessionBelongsToSchool($sessionId, $school);
            $this->assertCapacityAvailable($section, $sessionId, $actor, $override);

            $enrollment = null;
            if (!empty($options['enrollment_id'])) {
                $enrollment = Enrollment::query()->find($options['enrollment_id']);
                if (! $enrollment) {
                    throw ValidationException::withMessages([
                        'enrollment_id' => 'Enrollment not found.',
                    ]);
                }
                $this->assertEnrollmentContext($enrollment, $school, $student);
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
                reason: RegistrationNumberService::REASON_MANUAL,
                academicSessionId: $sessionId,
                closePrevious: true
            );
        });
    }

    public function changeSection(
        Student $student,
        School $school,
        string $destinationSectionId,
        User $actor,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $destinationSectionId, $actor, $options) {
            $this->assertStudentSchool($student, $school);
            $this->assertStudentIsActiveForPlacementChange($student);

            $current = StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if (!$current) {
                throw ValidationException::withMessages([
                    'placement' => 'Student has no current placement to change.',
                ]);
            }

            $sessionId = $options['academic_session_id'] ?? $current->academic_session_id;
            if ($sessionId !== $current->academic_session_id) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Section change must stay within the current academic session.',
                ]);
            }

            $newSection = ClassSection::query()->lockForUpdate()->findOrFail($destinationSectionId);
            if ((string) $newSection->school_id !== (string) $school->id) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Destination section does not belong to this school.',
                ]);
            }
            if ((string) $newSection->class_level_id !== (string) $current->class_level_id) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Section change must stay within the same class level. Use class change instead.',
                ]);
            }
            if ((string) $newSection->id === (string) $current->class_section_id) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Student is already in the selected section.',
                ]);
            }

            $override = (bool) ($options['capacity_override'] ?? false);
            $this->assertCapacityAvailable($newSection, $sessionId, $actor, $override);

            $current->update([
                'is_current' => false,
                'left_at' => now(),
                'notes' => trim(($current->notes ? $current->notes."\n" : '').($options['notes'] ?? 'Section change')),
            ]);

            $enrollment = $this->resolveActiveEnrollment($student, $school, $sessionId);

            return $this->createPlacement(
                student: $student,
                school: $school,
                enrollment: $enrollment,
                classLevelId: $current->class_level_id,
                section: $newSection,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: RegistrationNumberService::REASON_SECTION_CHANGE,
                academicSessionId: $sessionId,
                closePrevious: false
            );
        });
    }

    public function changeClass(
        Student $student,
        School $school,
        string $destinationClassLevelId,
        string $destinationSectionId,
        User $actor,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $destinationClassLevelId, $destinationSectionId, $actor, $options) {
            $this->assertStudentSchool($student, $school);
            $this->assertStudentIsActiveForPlacementChange($student);
            $this->assertClassLevelBelongsToSchool($destinationClassLevelId, $school);

            $current = StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if (!$current) {
                throw ValidationException::withMessages([
                    'placement' => 'Student has no current placement to change.',
                ]);
            }

            if ((string) $current->class_level_id === (string) $destinationClassLevelId) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'Student is already in the selected class level. Use section change instead.',
                ]);
            }

            $sessionId = $options['academic_session_id'] ?? $current->academic_session_id;
            $newSection = $this->resolveSection($destinationSectionId, $destinationClassLevelId, $school);
            $override = (bool) ($options['capacity_override'] ?? false);
            $this->assertCapacityAvailable($newSection, $sessionId, $actor, $override);

            $current->update([
                'is_current' => false,
                'left_at' => now(),
                'notes' => trim(($current->notes ? $current->notes."\n" : '').($options['notes'] ?? 'Class change')),
            ]);

            $enrollment = $this->resolveActiveEnrollment($student, $school, $sessionId);

            return $this->createPlacement(
                student: $student,
                school: $school,
                enrollment: $enrollment,
                classLevelId: $destinationClassLevelId,
                section: $newSection,
                actor: $actor,
                capacityOverride: $override,
                notes: $options['notes'] ?? null,
                reason: RegistrationNumberService::REASON_CLASS_CHANGE,
                academicSessionId: $sessionId,
                closePrevious: false
            );
        });
    }

    public function placeForPromotionOutcome(
        Student $student,
        School $school,
        string $academicSessionId,
        string $classLevelId,
        ?string $classSectionId,
        User $actor,
        string $outcome,
        array $options = []
    ): StudentSessionPlacement {
        return DB::transaction(function () use ($student, $school, $academicSessionId, $classLevelId, $classSectionId, $actor, $outcome, $options) {
            $this->assertStudentSchool($student, $school);
            $this->assertAcademicSessionBelongsToSchool($academicSessionId, $school);
            $this->assertClassLevelBelongsToSchool($classLevelId, $school);

            $override = (bool) ($options['capacity_override'] ?? false);
            $section = $this->resolveSectionForCapacity(
                $classLevelId,
                $classSectionId,
                $school,
                $academicSessionId,
                $actor,
                $override
            );

            $existing = StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $academicSessionId)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update([
                    'is_current' => false,
                    'left_at' => now(),
                    'notes' => trim(($existing->notes ? $existing->notes."\n" : '')."Replaced by {$outcome}"),
                ]);
            }

            $placement = StudentSessionPlacement::query()->create([
                'student_id' => $student->id,
                'school_id' => $school->id,
                'academic_session_id' => $academicSessionId,
                'class_level_id' => $classLevelId,
                'class_section_id' => $section->id,
                'enrollment_id' => $options['enrollment_id'] ?? null,
                'is_current' => true,
                'joined_at' => now(),
                'enrolled_at' => now(),
                'left_at' => null,
                'promotion_outcome' => $outcome,
                'notes' => $options['notes'] ?? null,
                'capacity_override_used' => $override,
                'meta' => array_merge($options['meta'] ?? [], [
                    'reason' => RegistrationNumberService::REASON_PROMOTION,
                    'via' => 'promotion',
                ]),
            ]);

            $cfg = $this->registrationNumbers->config($school);
            $shouldRegen = (bool) ($cfg['regenerate_on_promotion'] ?? true);

            if ($shouldRegen) {
                $number = $this->registrationNumbers->assign(
                    $student,
                    $school,
                    [
                        'academic_session_id' => $academicSessionId,
                        'class_level_id' => $classLevelId,
                        'class_section_id' => $section->id,
                        'placement_id' => $placement->id,
                        'enrollment_id' => $options['enrollment_id'] ?? null,
                    ],
                    RegistrationNumberService::REASON_PROMOTION,
                    $actor
                );
                $placement->registration_number = $number;
            } else {
                $current = $this->registrationNumbers->currentNumber($student, $school->id);
                $placement->registration_number = $current;
            }
            $placement->save();

            if (Schema::hasColumn('students', 'current_placement_id')) {
                $student->update(['current_placement_id' => $placement->id]);
            }

            return $placement->fresh();
        });
    }

    public function ensureAdmissionNumber(Student $student, School $school): string
    {
        if ($student->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'school' => 'Student does not belong to this school.',
            ]);
        }

        if (!empty($student->admission_number)) {
            return $student->admission_number;
        }

        return DB::transaction(function () use ($student, $school) {
            $locked = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

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

    protected function resolveSectionForCapacity(
        string $classLevelId,
        ?string $classSectionId,
        School $school,
        string $sessionId,
        User $actor,
        bool $override
    ): ClassSection {
        if ($classSectionId) {
            $section = $this->resolveSection($classSectionId, $classLevelId, $school);
            $this->assertCapacityAvailable($section, $sessionId, $actor, $override);
            return $section;
        }

        $sections = ClassSection::query()
            ->where('class_level_id', $classLevelId)
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->lockForUpdate()
            ->get();

        foreach ($sections as $section) {
            if ($this->hasCapacity($section, $sessionId, true)) {
                return $section;
            }
        }

        if ($override && $this->actorMayOverrideCapacity($actor) && $sections->isNotEmpty()) {
            return $sections->first();
        }

        throw ValidationException::withMessages([
            'class_section_id' => 'All sections for this class level are at capacity. An authorized capacity override with an explicit section is required.',
        ]);
    }

    protected function resolveSection(string $sectionId, string $classLevelId, School $school): ClassSection
    {
        $section = ClassSection::query()->findOrFail($sectionId);
        if ((string) $section->school_id !== (string) $school->id) {
            throw ValidationException::withMessages([
                'class_section_id' => 'Class section does not belong to this school.',
            ]);
        }
        if ((string) $section->class_level_id !== (string) $classLevelId) {
            throw ValidationException::withMessages([
                'class_section_id' => 'Selected section does not belong to the given class level.',
            ]);
        }
        return $section;
    }

    protected function hasCapacity(ClassSection $section, string $sessionId, bool $forUpdate = false): bool
    {
        $capacity = (int) ($section->capacity ?? 0);
        if ($capacity <= 0) {
            return true;
        }
        $q = StudentSessionPlacement::query()
            ->where('class_section_id', $section->id)
            ->where('academic_session_id', $sessionId)
            ->whereNull('left_at');
        if ($forUpdate) {
            $q->lockForUpdate();
        }
        return $q->count() < $capacity;
    }

    protected function assertCapacityAvailable(ClassSection $section, string $sessionId, User $actor, bool $override): void
    {
        if ($this->hasCapacity($section, $sessionId, true)) {
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
                return $actor->isAbleTo('placements.capacity_override') || $actor->isAbleTo('placements.override_capacity');
            }
            if (method_exists($actor, 'can')) {
                return $actor->can('placements.capacity_override') || $actor->can('placements.override_capacity');
            }
        } catch (\Throwable $e) {
            Log::debug('capacity override auth check failed', ['error' => $e->getMessage()]);
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
        string $academicSessionId,
        bool $closePrevious = true
    ): StudentSessionPlacement {
        if ($closePrevious) {
            StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->get()
                ->each(function (StudentSessionPlacement $p) {
                    $p->update(['is_current' => false, 'left_at' => now()]);
                });
        }

        $placement = StudentSessionPlacement::query()->create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'academic_session_id' => $academicSessionId,
            'class_level_id' => $classLevelId,
            'class_section_id' => $section->id,
            'enrollment_id' => $enrollment?->id,
            'is_current' => true,
            'joined_at' => now(),
            'enrolled_at' => now(),
            'left_at' => null,
            'notes' => $notes,
            'capacity_override_used' => $capacityOverride,
            'placed_by' => $actor->id,
            'meta' => [
                'reason' => $reason,
                'capacity_override' => $capacityOverride,
                'actor_id' => $actor->id,
            ],
        ]);

        $shouldAssign = true;
        if ($reason === RegistrationNumberService::REASON_SECTION_CHANGE) {
            $shouldAssign = $this->registrationNumbers->shouldRegenerateOnSectionChange($school);
        } elseif ($reason === RegistrationNumberService::REASON_CLASS_CHANGE) {
            $shouldAssign = $this->registrationNumbers->shouldRegenerateOnClassChange($school);
        } elseif ($reason === RegistrationNumberService::REASON_PROMOTION) {
            $cfg = $this->registrationNumbers->config($school);
            $shouldAssign = (bool) ($cfg['regenerate_on_promotion'] ?? true);
        }

        if ($shouldAssign) {
            $number = $this->registrationNumbers->assign(
                $student,
                $school,
                [
                    'academic_session_id' => $academicSessionId,
                    'class_level_id' => $classLevelId,
                    'class_section_id' => $section->id,
                    'placement_id' => $placement->id,
                    'enrollment_id' => $enrollment?->id,
                ],
                $reason,
                $actor
            );
            $placement->registration_number = $number;
            $placement->save();
        } else {
            $current = $this->registrationNumbers->currentNumber($student, $school->id);
            if ($current) {
                $placement->registration_number = $current;
                $placement->save();
            }
        }

        if (Schema::hasColumn('students', 'current_placement_id')) {
            $student->update(['current_placement_id' => $placement->id]);
        }

        return $placement->fresh();
    }

    protected function resolveActiveEnrollment(Student $student, School $school, string $sessionId): ?Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('school_id', $school->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->first();
    }

    protected function assertStudentIsActiveForPlacementChange(Student $student): void
    {
        if (in_array($student->status, ['withdrawn', 'transferred', 'graduated', 'deceased'], true)) {
            throw ValidationException::withMessages([
                'student' => 'Cannot change placement for a student in terminal status: '.$student->status,
            ]);
        }
    }

    protected function assertEnrollmentContext(Enrollment $enrollment, School $school, Student $student): void
    {
        if ((string) $enrollment->school_id !== (string) $school->id) {
            throw ValidationException::withMessages([
                'enrollment' => 'Enrollment does not belong to this school.',
            ]);
        }
        if ($enrollment->student_id && (string) $enrollment->student_id !== (string) $student->id) {
            throw ValidationException::withMessages([
                'enrollment' => 'Enrollment does not belong to this student.',
            ]);
        }
    }

    protected function assertClassLevelBelongsToSchool(string $classLevelId, School $school): void
    {
        $level = ClassLevel::query()->withoutGlobalScopes()->whereKey($classLevelId)->first();
        if (!$level) {
            throw ValidationException::withMessages([
                'class_level_id' => 'Class level not found.',
            ]);
        }
        $ok = false;
        if (isset($level->school_id) && (string) $level->school_id === (string) $school->id) {
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

    protected function assertAcademicSessionBelongsToSchool(string $sessionId, School $school): void
    {
        $session = DB::table('academic_sessions')->where('id', $sessionId)->first();
        if (!$session) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session not found.',
            ]);
        }
        if ((string) ($session->school_id ?? '') !== (string) $school->id) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session does not belong to this school.',
            ]);
        }
    }

    protected function assertStudentSchool(Student $student, School $school): void
    {
        if ((string) $student->school_id !== (string) $school->id) {
            throw ValidationException::withMessages([
                'student' => 'Student does not belong to this school.',
            ]);
        }
    }
}
