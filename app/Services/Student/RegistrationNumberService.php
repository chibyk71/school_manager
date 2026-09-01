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
 * Current uniqueness is enforced by registration_number_assignments
 * unique(school_id, scope_key, registration_number). History remains in
 * registration_number_histories (no uniqueness there so numbers can be reused
 * after effective_to is set).
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
     * Allocate a new registration number, release the student's previous current
     * assignment, write history, and claim the new number in the assignments table.
     *
     * Must be called inside a transaction (caller or allocate path).
     * Uniqueness is enforced by uq_regnum_assignment_active; collisions retry
     * with a fresh sequence value rather than relying on exists()-then-insert.
     */
    public function assign(
        Student $student,
        School $school,
        array $context,
        string $reason = self::REASON_INITIAL,
        ?User $actor = null
    ): string {
        $sessionId = $context['academic_session_id'] ?? null;
        $levelId = $context['class_level_id'] ?? null;
        $sectionId = $context['class_section_id'] ?? null;
        $scopeKey = $this->buildScopeKey($school, $sessionId, $levelId, $sectionId);
        $year = $this->resolveYear($sessionId);

        // Close any active history rows for this student at this school.
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

        // Release previous current assignment(s) for this student so the number
        // becomes available for reuse by others under the same scope.
        DB::table('registration_number_assignments')
            ->where('student_id', $student->id)
            ->where('school_id', $school->id)
            ->delete();

        $attempts = 0;
        $maxAttempts = 5;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $number = IdGenerator::generate('registration_number', $school, $year, $scopeKey);

            try {
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

                // Claim the number in the current-assignment table.
                // Unique constraint rejects concurrent claims of the same number.
                DB::table('registration_number_assignments')->insert([
                    'school_id' => $school->id,
                    'scope_key' => $scopeKey,
                    'registration_number' => $number,
                    'student_id' => $student->id,
                    'history_id' => $history->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $number;
            } catch (QueryException $e) {
                if (!$this->isUniqueConstraintViolation($e)) {
                    throw $e;
                }
                // Collision on assignment unique key — retry with next sequence value.
            }
        }

        throw ValidationException::withMessages([
            'registration_number' => 'Unable to allocate a unique registration number for the configured scope after multiple attempts.',
        ]);
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

    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());

        return in_array($code, ['1062', '23505'], true)
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
