<?php

namespace App\Services\Student;

use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Academic\AcademicSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StudentPlacementService
{
    public function placeInSession(Student $student, array $data): StudentSessionPlacement
    {
        $this->validatePlacementData($data);

        return DB::transaction(function () use ($student, $data) {
            $this->unsetCurrentPlacement($student);

            $placement = StudentSessionPlacement::create([
                'student_id' => $student->id,
                'academic_session_id' => $data['academic_session_id'],
                'class_level_id' => $data['class_level_id'],
                'class_section_id' => $data['class_section_id'] ?? null,
                'enrolled_at' => now()->toDateString(),
                'is_current' => true,
                'promotion_outcome' => $data['promotion_outcome'] ?? 'fresh_admission',
                'notes' => $data['notes'] ?? null,
            ]);

            Log::info('Student placed in session', [
                'student_id' => $student->id,
                'session_id' => $data['academic_session_id'],
                'class_level_id' => $data['class_level_id'],
                'class_section_id' => $data['class_section_id'] ?? null,
                'placement_id' => $placement->id,
            ]);

            return $placement;
        });
    }

    /**
     * Place a student for a session, or update an existing row for that session.
     * Used by promotion execution so re-runs / pre-existing next-session rows
     * do not hit UNIQUE(student_id, academic_session_id).
     */
    public function placeOrUpdateInSession(Student $student, array $data): StudentSessionPlacement
    {
        $this->validatePlacementData($data);

        return DB::transaction(function () use ($student, $data) {
            $existing = StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $data['academic_session_id'])
                ->first();

            if ($existing) {
                $this->unsetCurrentPlacement($student);

                $existing->update([
                    'class_level_id' => $data['class_level_id'],
                    'class_section_id' => $data['class_section_id'] ?? $existing->class_section_id,
                    'is_current' => true,
                    'left_at' => null,
                    'promotion_outcome' => $data['promotion_outcome'] ?? $existing->promotion_outcome,
                    'notes' => $data['notes'] ?? $existing->notes,
                ]);

                Log::info('Student placement updated for session (promotion reconcile)', [
                    'student_id' => $student->id,
                    'session_id' => $data['academic_session_id'],
                    'placement_id' => $existing->id,
                ]);

                return $existing->fresh();
            }

            return $this->placeInSession($student, $data);
        });
    }

    public function changeSection(Student $student, ClassSection $newSection, string $notes = null): StudentSessionPlacement
    {
        $currentPlacement = $student->currentPlacement;

        if (!$currentPlacement) {
            throw new \Exception('Student has no current placement to change.');
        }

        if ($currentPlacement->class_section_id === $newSection->id) {
            throw new \Exception('Student is already in this section.');
        }

        return DB::transaction(function () use ($currentPlacement, $newSection, $notes) {
            $currentPlacement->update([
                'class_section_id' => $newSection->id,
                'notes' => $notes ? ($currentPlacement->notes ? $currentPlacement->notes . "\n" : '') . $notes : $currentPlacement->notes,
            ]);

            Log::info('Student section changed', [
                'student_id' => $currentPlacement->student_id,
                'old_section_id' => $currentPlacement->getOriginal('class_section_id'),
                'new_section_id' => $newSection->id,
                'placement_id' => $currentPlacement->id,
            ]);

            return $currentPlacement->fresh();
        });
    }

    public function removeFromSection(Student $student): StudentSessionPlacement
    {
        $currentPlacement = $student->currentPlacement;

        if (!$currentPlacement) {
            throw new \Exception('Student has no current placement.');
        }

        $currentPlacement->update(['class_section_id' => null]);

        Log::info('Student removed from section', [
            'student_id' => $student->id,
            'placement_id' => $currentPlacement->id,
        ]);

        return $currentPlacement->fresh();
    }

    public function markAsLeft(Student $student, \Carbon\Carbon $leftAt = null, string $reason = null): StudentSessionPlacement
    {
        $currentPlacement = $student->currentPlacement;

        if (!$currentPlacement) {
            throw new \Exception('Student has no current placement to mark as left.');
        }

        $currentPlacement->update([
            'left_at' => $leftAt ?? now()->toDateString(),
            'is_current' => false,
            'notes' => $reason ? ($currentPlacement->notes ? $currentPlacement->notes . "\n" : '') . "Left: {$reason}" : $currentPlacement->notes,
        ]);

        Log::info('Student placement marked as left', [
            'student_id' => $student->id,
            'placement_id' => $currentPlacement->id,
            'reason' => $reason,
        ]);

        return $currentPlacement->fresh();
    }

    public function getCurrentPlacement(Student $student): ?StudentSessionPlacement
    {
        return $student->currentPlacement;
    }

    public function getPlacementHistory(Student $student)
    {
        return $student->sessionPlacements()
            ->with(['academicSession', 'classLevel', 'classSection'])
            ->orderBy('academic_session_id', 'desc')
            ->get();
    }

    private function validatePlacementData(array $data): void
    {
        if (empty($data['academic_session_id']) || empty($data['class_level_id'])) {
            throw new ValidationException(validator([], [
                'academic_session_id' => 'required',
                'class_level_id' => 'required',
            ]));
        }
    }

    private function unsetCurrentPlacement(Student $student): void
    {
        StudentSessionPlacement::where('student_id', $student->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }
}
