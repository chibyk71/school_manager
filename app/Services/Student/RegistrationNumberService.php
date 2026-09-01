<?php

namespace App\Services\Student;

use App\Helpers\IdGenerator;
use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\Student\RegistrationNumberHistory;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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

    public function buildScopeKey(School $school, ?string $sessionId, ?string $levelId, ?string $sectionId): string
    {
        $scope = $this->config($school)['scope'];

        return match ($scope) {
            self::SCOPE_SCHOOL_SESSION => implode(':', [$school->id, $sessionId ?? '']),
            self::SCOPE_SCHOOL_SESSION_LEVEL => implode(':', [$school->id, $sessionId ?? '', $levelId ?? '']),
            self::SCOPE_SCHOOL_LEVEL => implode(':', [$school->id, $levelId ?? '']),
            self::SCOPE_SCHOOL_SECTION => implode(':', [$school->id, $sectionId ?? '']),
            default => implode(':', [$school->id, $sessionId ?? '', $sectionId ?? '']),
        };
    }

    public function assign(Student $student, School $school, array $context, string $reason = self::REASON_INITIAL, ?User $actor = null): string
    {
        $sessionId = $context['academic_session_id'] ?? null;
        $levelId = $context['class_level_id'] ?? null;
        $sectionId = $context['class_section_id'] ?? null;
        $scopeKey = $this->buildScopeKey($school, $sessionId, $levelId, $sectionId);
        $year = $this->resolveYear($sessionId);

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

        $number = IdGenerator::generate('registration_number', $school, $year, $scopeKey);

        $exists = RegistrationNumberHistory::query()
            ->where('school_id', $school->id)
            ->where('scope_key', $scopeKey)
            ->where('registration_number', $number)
            ->whereNull('effective_to')
            ->exists();

        if ($exists) {
            $number = IdGenerator::generate('registration_number', $school, $year, $scopeKey);
        }

        RegistrationNumberHistory::query()->create([
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
            'assigned_by' => $actor?->id,
            'meta' => $context['meta'] ?? null,
        ]);

        return $number;
    }

    public function regenerate(Student $student, School $school, StudentSessionPlacement $placement, User $actor, ?string $notes = null): string
    {
        if ($student->school_id !== $school->id) {
            throw ValidationException::withMessages(['school' => 'Student does not belong to this school.']);
        }
        if ($placement->student_id !== $student->id) {
            throw ValidationException::withMessages(['placement' => 'Placement does not belong to this student.']);
        }

        $number = $this->assign($student, $school, [
            'academic_session_id' => $placement->academic_session_id,
            'class_level_id' => $placement->class_level_id,
            'class_section_id' => $placement->class_section_id,
            'placement_id' => $placement->id,
            'enrollment_id' => $placement->enrollment_id,
            'meta' => ['notes' => $notes],
        ], self::REASON_REGENERATE, $actor);

        $placement->registration_number = $number;
        $placement->save();

        return $number;
    }

    public function currentNumber(Student $student, ?string $schoolId = null): ?string
    {
        $q = RegistrationNumberHistory::query()->where('student_id', $student->id)->whereNull('effective_to')->orderByDesc('effective_from');
        if ($schoolId) {
            $q->where('school_id', $schoolId);
        }

        return $q->value('registration_number');
    }

    public function history(Student $student, ?string $schoolId = null)
    {
        $q = RegistrationNumberHistory::query()->where('student_id', $student->id)->orderByDesc('effective_from');
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
        }

        return (int) now()->year;
    }
}
