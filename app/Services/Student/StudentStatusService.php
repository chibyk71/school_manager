<?php

namespace App\Services\Student;

use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * StudentStatusService – Student Lifecycle Status Management (v2.0 – Production-Ready)
 *
 * Handles all status transitions for a student (admitted → enrolled → active → suspended → withdrawn → graduated, etc.).
 *
 * This service enforces business rules and maintains data consistency across related records
 * (especially StudentSessionPlacement when a student leaves a placement).
 *
 * Supported Status Transitions (as defined in Student model + HasDynamicEnum):
 *   admitted → enrolled → active
 *   active → suspended → reinstate
 *   active/enrolled → withdrawn / transferred / graduated / deceased
 *
 * Features / Problems Solved:
 * - Centralized, auditable status change logic with proper validation.
 * - Automatic handling of placement `left_at` and `is_current` flag when student leaves.
 * - Transactional safety for all status changes.
 * - Detailed logging for compliance and debugging.
 * - Clear exception messages for frontend feedback.
 * - Prepares for events (StudentStatusChanged) and notifications.
 *
 * Fits into the Student Management Module:
 * - Called by StudentStatusController (activate, suspend, withdraw, graduate, etc.).
 * - Works closely with StudentPlacementService when marking placements as left.
 * - Used in frontend: Student status badges, bulk actions, Student Show page actions.
 * - Integrates with HasDynamicEnum (status field) and Student model helpers.
 */

class StudentStatusService
{
    protected StudentPlacementService $placementService;

    public function __construct(StudentPlacementService $placementService)
    {
        $this->placementService = $placementService;
    }

    /**
     * Activate a student (usually after enrollment)
     */
    public function activate(Student $student, User $changedBy): void
    {
        $this->validateTransition($student, 'active');

        DB::transaction(function () use ($student, $changedBy) {
            $student->update([
                'status' => 'active',
                'status_reason' => null,
                'status_date' => now()->toDateString(),
                'status_until' => null,
                'status_changed_by' => $changedBy->id,
            ]);

            Log::info('Student activated', [
                'student_id' => $student->id,
                'changed_by' => $changedBy->id,
            ]);
        });

        // TODO: Fire StudentStatusChanged event
    }

    /**
     * Suspend a student (temporary exclusion)
     */
    public function suspend(Student $student, string $reason, ?Carbon $until, User $changedBy): void
    {
        $this->validateTransition($student, 'suspended');

        DB::transaction(function () use ($student, $reason, $until, $changedBy) {
            $student->update([
                'status' => 'suspended',
                'status_reason' => $reason,
                'status_date' => now()->toDateString(),
                'status_until' => $until?->toDateString(),
                'status_changed_by' => $changedBy->id,
            ]);

            Log::info('Student suspended', [
                'student_id' => $student->id,
                'reason' => $reason,
                'until' => $until?->toDateString(),
                'changed_by' => $changedBy->id,
            ]);
        });

        // TODO: Fire StudentStatusChanged event + notify guardians
    }

    /**
     * Reinstate a suspended student
     */
    public function reinstate(Student $student, User $changedBy): void
    {
        if ($student->status !== 'suspended') {
            throw new \Exception('Only suspended students can be reinstated.');
        }

        $this->activate($student, $changedBy); // Reuse activation logic
    }

    /**
     * Withdraw a student (voluntary exit)
     */
    public function withdraw(Student $student, string $reason, Carbon $date, User $changedBy): void
    {
        $this->validateTransition($student, 'withdrawn');

        DB::transaction(function () use ($student, $reason, $date, $changedBy) {
            // Mark current placement as left
            if ($student->currentPlacement) {
                $this->placementService->markAsLeft($student, $date, "Withdrawn: {$reason}");
            }

            $student->update([
                'status' => 'withdrawn',
                'status_reason' => $reason,
                'status_date' => $date->toDateString(),
                'status_changed_by' => $changedBy->id,
            ]);

            Log::info('Student withdrawn', [
                'student_id' => $student->id,
                'reason' => $reason,
                'date' => $date->toDateString(),
                'changed_by' => $changedBy->id,
            ]);
        });

        // TODO: Fire StudentStatusChanged event + notify guardians
    }

    /**
     * Mark student as graduated
     */
    public function markGraduated(Student $student, Carbon $date, User $changedBy): void
    {
        $this->validateTransition($student, 'graduated');

        DB::transaction(function () use ($student, $date, $changedBy) {
            if ($student->currentPlacement) {
                $this->placementService->markAsLeft($student, $date, 'Graduated');
            }

            $student->update([
                'status' => 'graduated',
                'status_date' => $date->toDateString(),
                'status_changed_by' => $changedBy->id,
            ]);

            Log::info('Student marked as graduated', [
                'student_id' => $student->id,
                'date' => $date->toDateString(),
                'changed_by' => $changedBy->id,
            ]);
        });
    }

    /**
     * Mark student as deceased (sensitive operation)
     */
    public function markDeceased(Student $student, Carbon $date, string $notes, User $changedBy): void
    {
        DB::transaction(function () use ($student, $date, $notes, $changedBy) {
            if ($student->currentPlacement) {
                $this->placementService->markAsLeft($student, $date, 'Deceased');
            }

            $student->update([
                'status' => 'deceased',
                'status_reason' => $notes,
                'status_date' => $date->toDateString(),
                'status_changed_by' => $changedBy->id,
            ]);

            Log::warning('Student marked as deceased', [
                'student_id' => $student->id,
                'date' => $date->toDateString(),
                'changed_by' => $changedBy->id,
            ]);
        });

        // TODO: Fire event with special handling (restricted access, etc.)
    }

    /**
     * Transfer student out of the school (within or outside tenant)
     */
    public function transferOut(Student $student, string $destination, string $reason, User $changedBy): void
    {
        $this->validateTransition($student, 'transferred');

        DB::transaction(function () use ($student, $destination, $reason, $changedBy) {
            if ($student->currentPlacement) {
                $this->placementService->markAsLeft($student, now(), "Transferred to: {$destination}");
            }

            $student->update([
                'status' => 'transferred',
                'status_reason' => $reason,
                'transfer_destination' => $destination,
                'status_date' => now()->toDateString(),
                'status_changed_by' => $changedBy->id,
            ]);

            Log::info('Student transferred out', [
                'student_id' => $student->id,
                'destination' => $destination,
                'changed_by' => $changedBy->id,
            ]);
        });
    }

    // =================================================================
    // Private Validation
    // =================================================================

    private function validateTransition(Student $student, string $newStatus): void
    {
        $allowedFrom = match ($newStatus) {
            'active' => ['admitted', 'enrolled', 'suspended'],
            'suspended' => ['active', 'enrolled'],
            'withdrawn',
            'transferred',
            'graduated',
            'deceased' => ['active', 'enrolled', 'admitted'],
            default => [],
        };

        if (!in_array($student->status, $allowedFrom)) {
            throw new ValidationException(validator([], [
                'status' => "Cannot change status from '{$student->status}' to '{$newStatus}'.",
            ]));
        }

        if ($student->status === $newStatus) {
            throw new ValidationException(validator([], [
                'status' => "Student is already '{$newStatus}'.",
            ]));
        }
    }
}
