<?php

namespace App\Http\Controllers\Student;

use App\Models\Student\Student;
use App\Services\Student\StudentStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * StudentStatusController – Manage Student Enrollment Status Transitions
 *
 * Handles all status changes for an enrolled student:
 *   - activate     → marks student as active after suspension/probation
 *   - suspend      → temporarily suspends the student
 *   - graduate     → marks as graduated (end of school career)
 *   - withdraw     → student withdrawn by parent or school
 *   - transfer     → initiates an inter-school transfer
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Uses StudentPolicy::changeStatus() → requires `students.change-status`.
 * Status is a sensitive field — only academic staff and admin can change it.
 *
 * ── Business Logic ────────────────────────────────────────────────────────────
 * StudentStatusService enforces valid state transitions (e.g., you cannot
 * re-activate an already active student) and fires StudentStatusChanged events.
 * Notifications are dispatched via listeners.
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * - Route: POST /students/{student}/status
 * - Frontend: Students/Show.vue status dropdown or dedicated action buttons
 * - Policy: StudentPolicy::changeStatus()
 * - Events: StudentStatusChanged → NotifyOnStudentStatusChange listener
 */
class StudentStatusController
{
    public function __construct(
        protected StudentStatusService $statusService
    ) {}

    /**
     * Change a student's enrollment status.
     * POST /students/{student}/status
     *
     * Expected payload:
     *   status: activate | suspend | graduate | withdraw | transfer
     *   reason: string (required for suspend/withdraw/transfer)
     *   destination: string (required for transfer — independent of reason)
     *   effective_date: date (optional, defaults to today)
     */
    public function update(Request $request, Student $student)
    {
        Gate::authorize('changeStatus', $student);

        $request->validate([
            'status'         => ['required', 'string', 'in:activate,suspend,graduate,withdraw,transfer'],
            'reason'         => ['required_if:status,suspend,withdraw,transfer', 'nullable', 'string', 'min:10', 'max:1000'],
            'destination'    => ['required_if:status,transfer', 'nullable', 'string', 'min:2', 'max:255'],
            'effective_date' => ['nullable', 'date'],
        ]);

        try {
            $this->statusService->changeStatus(
                student:       $student,
                newStatus:     $request->string('status'),
                reason:        $request->input('reason'),
                effectiveDate: $request->date('effective_date') ?? now(),
                changedBy:     auth()->user(),
                destination:   $request->input('destination'),
            );

            $label = ucfirst($request->string('status'));

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Student status changed to {$label}.",
                    'status'  => $student->fresh()->status,
                ]);
            }

            return back()->with('success', "Student status updated to {$label} successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());

        } catch (\Exception $e) {
            Log::error('Failed to change student status', [
                'student_id'     => $student->id,
                'requested_status' => $request->input('status'),
                'user_id'        => auth()->id(),
                'error'          => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
