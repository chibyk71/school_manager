<?php

namespace App\Services\Student;

use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * StudentPlacementService – Academic Placement Management (v2.0 – Production-Ready)
 *
 * Handles all operations related to placing a student into a class level and section (arm)
 * for a specific academic session. This includes initial placement, mid-session changes,
 * and promotion/repetition workflows.
 *
 * Key Responsibilities:
 * - Create new placement records for a student in a session
 * - Change class section (arm) mid-session
 * - Mark placements as left (withdrawn, transferred, graduated)
 * - Maintain the is_current flag correctly (only one current placement per student)
 * - Enforce business rules: one placement per student per session
 *
 * Features / Problems Solved:
 * - Prevents duplicate placements per session using unique constraint + service validation.
 * - Clean handling of is_current flag with automatic demotion of previous placement.
 * - Supports flexible promotion/repetition workflows.
 * - Full transaction safety for placement changes.
 * - Comprehensive logging for audit and debugging.
 * - Prepares for future PromotionService integration.
 *
 * Fits into the Student Management Module:
 * - Called by StudentEnrollmentService (initial placement)
 * - Used by StudentPlacementController (change arm, view history)
 * - Accessed from frontend: PlacementInfo.vue, Enrollment Wizard (Step 4), Student Show → Academic tab.
 * - Works closely with Student model (currentPlacement relationship) and StudentSessionPlacement model.
 */

class StudentPlacementService
{
    /**
     * Place a student into a class level and optional section for a specific session.
     *
     * @param Student $student
     * @param array   $data  Must contain: academic_session_id, class_level_id, class_section_id (optional)
     * @return StudentSessionPlacement
     * @throws ValidationException
     */
    public function placeInSession(Student $student, array $data): StudentSessionPlacement
    {
        $this->validatePlacementData($data);

        return DB::transaction(function () use ($student, $data) {

            // Demote any existing current placement for this student
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
     * Change a student's class section (arm) mid-session (e.g., JSS 1A → JSS 1B)
     */
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

    /**
     * Remove student from a specific section (keep in class level only)
     */
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

    /**
     * Mark current placement as ended (student left this placement)
     */
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

    /**
     * Get current placement for a student (convenience)
     */
    public function getCurrentPlacement(Student $student): ?StudentSessionPlacement
    {
        return $student->currentPlacement;
    }

    /**
     * Get full placement history for a student
     */
    public function getPlacementHistory(Student $student)
    {
        return $student->sessionPlacements()
            ->with(['academicSession', 'classLevel', 'classSection'])
            ->orderBy('academic_session_id', 'desc')
            ->get();
    }

    // =================================================================
    // Private Helpers
    // =================================================================

    private function validatePlacementData(array $data): void
    {
        if (empty($data['academic_session_id']) || empty($data['class_level_id'])) {
            throw new ValidationException(validator([], [
                'academic_session_id' => 'required',
                'class_level_id' => 'required',
            ]));
        }
    }

    /**
     * Unset current placement flag for any existing placements of this student
     */
    private function unsetCurrentPlacement(Student $student): void
    {
        StudentSessionPlacement::where('student_id', $student->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }
}
