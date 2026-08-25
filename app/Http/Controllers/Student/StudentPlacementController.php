<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\PlaceStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Models\Academic\Student;
use App\Services\Student\StudentPlacementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * StudentPlacementController – Assign and Manage Class Section Placements
 *
 * Handles assigning a student to a class section within the current or a
 * specified academic session. A student can be re-placed (e.g., moved from
 * JSS 1A to JSS 1B) without creating a new student record.
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Uses StudentPolicy::place() → requires `students.place` permission.
 * Only admin and academic staff can reassign students.
 *
 * ── Business Logic ────────────────────────────────────────────────────────────
 * All placement logic (session validation, duplicate check, history tracking)
 * is handled by StudentPlacementService. This controller only handles HTTP.
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * - Route: POST /students/{student}/placement
 * - Route: DELETE /students/{student}/placement  (remove from current section)
 * - Frontend: Students/Show.vue placement tab, or a dedicated modal
 * - Policy: StudentPolicy::place()
 */
class StudentPlacementController
{
    public function __construct(
        protected StudentPlacementService $placementService
    ) {}

    /**
     * Assign or re-assign a student to a class section.
     * POST /students/{student}/placement
     *
     * Creates a session placement record and updates the student's
     * current_class_section_id. Fires StudentPlaced event.
     */
    public function store(PlaceStudentRequest $request, Student $student)
    {
        Gate::authorize('place', $student);

        try {
            $placement = $this->placementService->place(
                student:        $student,
                classSectionId: $request->validated('class_section_id'),
                sessionId:      $request->validated('academic_session_id'),
                notes:          $request->validated('notes'),
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message'   => 'Student placed successfully.',
                    'placement' => $placement,
                ]);
            }

            return back()->with('success', "Student assigned to {$placement->classSection->display_name} successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());

        } catch (\Exception $e) {
            Log::error('Failed to place student', [
                'student_id' => $student->id,
                'user_id'    => auth()->id(),
                'error'      => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove a student from their current class section assignment.
     * DELETE /students/{student}/placement
     *
     * This nulls out the current placement but keeps the history record intact.
     * Used when a placement was made in error, not as part of a promotion flow.
     */
    public function destroy(Request $request, Student $student)
    {
        Gate::authorize('place', $student);

        try {
            $this->placementService->removePlacement($student);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Placement removed.']);
            }

            return back()->with('success', 'Student removed from class section.');

        } catch (\Exception $e) {
            Log::error('Failed to remove student placement', [
                'student_id' => $student->id,
                'user_id'    => auth()->id(),
                'error'      => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Unable to remove placement.']);
        }
    }
}
