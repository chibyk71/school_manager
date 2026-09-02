<?php

namespace App\Services\Student;

use App\Helpers\IdGenerator;
use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\Student\RegistrationNumberHistory;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration Number — mutable register identity within a school-configured scope.
 *
 * Current uniqueness is enforced by registration_number_assignments:
 *   unique(school_id, scope_key, registration_number) — number uniqueness in scope
 *   unique(school_id, student_id) — one current number per student per school
 *
 * History remains in registration_number_histories (no uniqueness there so numbers
 * can be reused after effective_to is set).
 *
 * Phase 5 invariant — current assignment:
 *   A student has at most one current registration number per school.
 *   Enforced at the database (uq_regnum_assignment_student) and by locking the
 *   Student row inside assign()'s transaction so concurrent empty-state claims serialize.
 */
class RegistrationNumberService
{
    public const SCOPE_SCHOOL_SESSION = 'school_session';
    public const SCOPE_SCHOOL_SESSION_LEVEL = 'school_session_level';
    public const SCOPE_SCHOOL_SESSION_SECTION = 'school_session_section';
    public const SCOPE_SCHOOL_LEVEL = 'school_level';
    public const SCOPE_SCHOOL_SECTION = 'school_section';

    public const REASON_INITIAL = 'initial';
    public const REASON_SECTION_CHANGE = 'section_change';
    public const REASON_MANUAL = 'manual';
    public const REASON_REGENERATE = 'regenerate';

    public function config(School $school): array
    {
        $settings = getMergedSettings('academic.registration_number', $school) ?? [];

        return [
            'scope' => $settings['scope'] ?? self::SCOPE_SCHOOL_SESSION_SECTION,
            'regenerate_on_section_change' => (bool) ($settings['regenerate_on_section_change'] ?? true),
            'regenerate_on_session_change' => (bool) ($settings['regenerate_on_session_change'] ?? true),
            'regenerate_on_promotion' => (bool) ($settings['regenerate_on_promotion'] ?? true),
        ];
    }

    public function buildScopeKey(
        School $school,
        ?string $sessionId,
        ?string $levelId,
        ?string $sectionId
    ): string {
        $scope = $this->config($school)['scope'];

        return match ($scope) {
            self::SCOPE_SCHOOL_SESSION => implode(':', [$school->id, $sessionId ?? '']),
            self::SCOPE_SCHOOL_SESSION_LEVEL => implode(':', [$school->id, $sessionId ?? '', $levelId ?? '']),
            self::SCOPE_SCHOOL_LEVEL => implode(':', [$school->id, $levelId ?? '']),
            self::SCOPE_SCHOOL_SECTION => implode(':', [$school->id, $sectionId ?? '']),
            default => implode(':', [$school->id, $sessionId ?? '', $sectionId ?? '']),
        };
    }

    /**
     * Allocate a new registration number for the student at this school.
     *
     * Establishes (or nests under) a DB transaction for the full mutation:
     * student lock, release previous assignment, sequence allocate, claim.
     *
     * Context IDs (academic_session_id, class_level_id, class_section_id) must
     * belong to the given school. Student must belong to the given school.
     */
    public function assign(
        Student $student,
        School $school,
        array $context,
        string $reason = self::REASON_INITIAL,
        ?User $actor = null
    ): string {
        if ($student->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'school' => 'Student does not belong to this school.',
            ]);
        }

        $this->assertContextBelongsToSchool($school, $student, $context);

        return DB::transaction(function () use ($student, $school, $context, $reason, $actor) {
            $sessionId = $context['academic_session_id'] ?? null;
            $levelId = $context['class_level_id'] ?? null;
            $sectionId = $context['class_section_id'] ?? null;
            $scopeKey = $this->buildScopeKey($school, $sessionId, $levelId, $sectionId);
            $year = $this->resolveYear($sessionId);

            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            $this->releaseCurrentAssignment($student, $school);

            $attempts = 0;
            $maxAttempts = 5;

            while ($attempts < $maxAttempts) {
                $attempts++;
                $number = IdGenerator::generate('registration_number', $school, $year, $scopeKey);

                try {
                    DB::transaction(function () use (
                        $student,
                        $school,
                        $context,
                        $reason,
                        $actor,
                        $number,
                        $scopeKey,
                        $sessionId,
                        $levelId,
                        $sectionId
                    ) {
                        $history = RegistrationNumberHistory::query()->create([
                            'student_id' => $student->id,
                            'school_id' => $school->id,
                            'enrollment_id' => $context['enrollment_id'] ?? null,
                            'placement_id' => $context['placement_id'] ?? null,
                            'registration_number' => $number,
                            'scope_key' => $scopeKey,
                            'academic_session_id' => $sessionId,
                            'class_level_id' => $levelId,
                            'class_section_id' => $sectionId,
                            'reason' => $reason,
                            'effective_from' => now(),
                            'effective_to' => null,
                            'assigned_by' => $actor?->id,
                            'meta' => $context['meta'] ?? null,
                        ]);

                        DB::table('registration_number_assignments')->insert([
                            'school_id' => $school->id,
                            'scope_key' => $scopeKey,
                            'registration_number' => $number,
                            'student_id' => $student->id,
                            'history_id' => $history->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    });

                    return $number;
                } catch (QueryException $e) {
                    if (!$this->isUniqueConstraintViolation($e)) {
                        throw $e;
                    }
                }
            }

            throw ValidationException::withMessages([
                'registration_number' => 'Unable to allocate a unique registration number for the configured scope after multiple attempts.',
            ]);
        });
    }

    protected function releaseCurrentAssignment(Student $student, School $school): void
    {
        RegistrationNumberHistory::query()
            ->where('student_id', $student->id)
            ->where('school_id', $school->id)
            ->whereNull('effective_to')
            ->lockForUpdate()
            ->get()
            ->each(function (RegistrationNumberHistory $row) {
                $row->effective_to = now();
                $row->save();
            });

        DB::table('registration_number_assignments')
            ->where('student_id', $student->id)
            ->where('school_id', $school->id)
            ->delete();
    }

    public function regenerate(
        Student $student,
        School $school,
        StudentSessionPlacement $placement,
        User $actor,
        ?string $notes = null
    ): string {
        if ($student->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'school' => 'Student does not belong to this school.',
            ]);
        }
        if ($placement->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'placement' => 'Placement does not belong to this student.',
            ]);
        }

        return DB::transaction(function () use ($student, $school, $placement, $actor, $notes) {
            $number = $this->assign(
                $student,
                $school,
                [
                    'academic_session_id' => $placement->academic_session_id,
                    'class_level_id' => $placement->class_level_id,
                    'class_section_id' => $placement->class_section_id,
                    'placement_id' => $placement->id,
                    'enrollment_id' => $placement->enrollment_id,
                    'meta' => ['notes' => $notes],
                ],
                self::REASON_REGENERATE,
                $actor
            );

            $placement->registration_number = $number;
            $placement->save();

            return $number;
        });
    }

    public function currentNumber(Student $student, ?string $schoolId = null): ?string
    {
        $q = DB::table('registration_number_assignments')
            ->where('student_id', $student->id)
            ->orderByDesc('id');

        if ($schoolId) {
            $q->where('school_id', $schoolId);
        }

        return $q->value('registration_number');
    }

    public function history(Student $student, ?string $schoolId = null)
    {
        $q = RegistrationNumberHistory::query()
            ->where('student_id', $student->id)
            ->orderByDesc('effective_from');

        if ($schoolId) {
            $q->where('school_id', $schoolId);
        }

        return $q->get();
    }

    public function shouldRegenerateOnSectionChange(School $school): bool
    {
        return (bool) $this->config($school)['regenerate_on_section_change'];
    }

    protected function resolveYear(?string $sessionId): int
    {
        if ($sessionId) {
            $session = AcademicSession::query()->find($sessionId);
            if ($session && !empty($session->start_date)) {
                return (int) date('Y', strtotime((string) $session->start_date));
            }
            if ($session && !empty($session->name) && preg_match('/(20\d{2})/', (string) $session->name, $m)) {
                return (int) $m[1];
            }
        }

        return (int) now()->year;
    }

    /**
     * Ensure optional academic_session / class_level / class_section / enrollment /
     * placement IDs in context belong to the given school and student so history
     * cannot be written with foreign or internally inconsistent context.
     */
    protected function assertContextBelongsToSchool(School $school, Student $student, array $context): void
    {
        $sessionId = $context['academic_session_id'] ?? null;
        $levelId = $context['class_level_id'] ?? null;
        $sectionId = $context['class_section_id'] ?? null;
        $enrollmentId = $context['enrollment_id'] ?? null;
        $placementId = $context['placement_id'] ?? null;

        if ($sessionId) {
            $session = DB::table('academic_sessions')->where('id', $sessionId)->first();
            if (!$session) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session not found.',
                ]);
            }
            if (($session->school_id ?? null) !== $school->id) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session does not belong to this school.',
                ]);
            }
        }

        if ($levelId) {
            $level = DB::table('class_levels')->where('id', $levelId)->first();
            if (!$level) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'Class level not found.',
                ]);
            }
            $ok = false;
            if (isset($level->school_id) && $level->school_id === $school->id) {
                $ok = true;
            } elseif (!empty($level->school_section_id)) {
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

        if ($sectionId) {
            $section = DB::table('class_sections')->where('id', $sectionId)->first();
            if (!$section) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Class section not found.',
                ]);
            }
            if (($section->school_id ?? null) !== $school->id) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Class section does not belong to this school.',
                ]);
            }
            if ($levelId && ($section->class_level_id ?? null) !== $levelId) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Class section does not belong to the target class level.',
                ]);
            }
        }

        if ($enrollmentId) {
            $enrollment = DB::table('enrollments')->where('id', $enrollmentId)->first();
            if (!$enrollment) {
                throw ValidationException::withMessages([
                    'enrollment_id' => 'Enrollment not found.',
                ]);
            }
            if (($enrollment->school_id ?? null) !== $school->id) {
                throw ValidationException::withMessages([
                    'enrollment_id' => 'Enrollment does not belong to this school.',
                ]);
            }
            if (!empty($enrollment->student_id) && $enrollment->student_id !== $student->id) {
                throw ValidationException::withMessages([
                    'enrollment_id' => 'Enrollment does not belong to this student.',
                ]);
            }
            if ($sessionId && !empty($enrollment->academic_session_id)
                && $enrollment->academic_session_id !== $sessionId) {
                throw ValidationException::withMessages([
                    'enrollment_id' => 'Enrollment does not belong to the specified academic session.',
                ]);
            }
        }

        if ($placementId) {
            $placement = DB::table('student_session_placements')->where('id', $placementId)->first();
            if (!$placement) {
                throw ValidationException::withMessages([
                    'placement_id' => 'Placement not found.',
                ]);
            }
            if (($placement->student_id ?? null) !== $student->id) {
                throw ValidationException::withMessages([
                    'placement_id' => 'Placement does not belong to this student.',
                ]);
            }
            if ($sessionId && ($placement->academic_session_id ?? null) !== $sessionId) {
                throw ValidationException::withMessages([
                    'placement_id' => 'Placement does not belong to the specified academic session.',
                ]);
            }
            if ($levelId && ($placement->class_level_id ?? null) !== $levelId) {
                throw ValidationException::withMessages([
                    'placement_id' => 'Placement does not belong to the specified class level.',
                ]);
            }
            if ($sectionId && ($placement->class_section_id ?? null) !== $sectionId) {
                throw ValidationException::withMessages([
                    'placement_id' => 'Placement does not belong to the specified class section.',
                ]);
            }
        }
    }

    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return in_array($code, ['1062', '23505'], true)
            || $sqlState === '23505'
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
