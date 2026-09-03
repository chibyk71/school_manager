<?php

namespace App\Services\Student;

use App\Models\Student\Enrollment;
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
     * Withdraw a student (voluntary exit).
     * Phase 6: also ends active Enrollment(s) for the school without deleting history.
     */
    public function withdraw(Student $student, string $reason, Carbon $date, User $changedBy): void
    {
        $this->validateTransition($student, 'withdrawn');

        DB::transaction(function () use ($student, $reason, $date, $changedBy) {
            if ($student->currentPlacement) {
                $this->placementService->markAsLeft($student, $date, "Withdrawn: {$reason}");
            }

            $this->closeActiveEnrollments($student, Enrollment::STATUS_WITHDRAWN, [
                'withdrawn_at' => $date,
                'notes' => $reason,
            ]);

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
    }

    /**
     * Mark student as graduated (formal completion).
     * Prefer invoking via Promotion module for academic graduation; this remains
     * the shared status primitive used by ProcessStudentPromotion::applyGraduate.
     */
    public function markGraduated(Student $student, Carbon $date, User $changedBy): void
    {
        $this->validateTransition($student, 'graduated');

        DB::transaction(function () use ($student, $date, $changedBy) {
            if ($student->currentPlacement) {
                $this->placementService->markAsLeft($student, $date, 'Graduated');
            }

            $this->closeActiveEnrollments($student, Enrollment::STATUS_COMPLETED, [
                'completed_at' => $date,
                'notes' => 'Graduated',
            ]);

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
     * Transfer student out of the school (within or outside tenant).
     * Phase 6: ends active Enrollment as transferred_out; preserves history.
     */
    public function transferOut(
        Student $student,
        string $destination,
        string $reason,
        User $changedBy,
        ?Carbon $effectiveDate = null
    ): void {
        $this->validateTransition($student, 'transferred');

        $date = $effectiveDate ?? now();

        DB::transaction(function () use ($student, $destination, $reason, $changedBy, $date) {
            if ($student->currentPlacement) {
                $this->placementService->markAsLeft($student, $date, "Transferred to: {$destination}");
            }

            $this->closeActiveEnrollments($student, Enrollment::STATUS_TRANSFERRED_OUT, [
                'transferred_out_at' => $date,
                'notes' => $reason,
                'meta' => ['transfer_destination' => $destination],
            ]);

            $student->update([
                'status' => 'transferred',
                'status_reason' => $reason,
                'transfer_destination' => $destination,
                'status_date' => $date->toDateString(),
                'status_changed_by' => $changedBy->id,
            ]);

            Log::info('Student transferred out', [
                'student_id' => $student->id,
                'destination' => $destination,
                'changed_by' => $changedBy->id,
            ]);
        });
    }

    /**
     * Controller-facing dispatcher used by StudentStatusController.
     */
    public function changeStatus(
        Student $student,
        string $newStatus,
        ?string $reason,
        Carbon $effectiveDate,
        User $changedBy
    ): void {
        match ($newStatus) {
            'activate' => $this->activate($student, $changedBy),
            'suspend' => $this->suspend($student, (string) $reason, null, $changedBy),
            'graduate' => $this->markGraduated($student, $effectiveDate, $changedBy),
            'withdraw' => $this->withdraw($student, (string) $reason, $effectiveDate, $changedBy),
            'transfer' => $this->transferOut(
                $student,
                $reason ?? 'External transfer',
                (string) $reason,
                $changedBy,
                $effectiveDate
            ),
            default => throw ValidationException::withMessages([
                'status' => "Unsupported status action: {$newStatus}",
            ]),
        };
    }

    /**
     * End active enrollments for the student at their school.
     * Does not delete historical Enrollment rows.
     */
    protected function closeActiveEnrollments(Student $student, string $enrollmentStatus, array $attrs = []): void
    {
        $query = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('school_id', $student->school_id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->lockForUpdate();

        $query->get()->each(function (Enrollment $enrollment) use ($enrollmentStatus, $attrs) {
            $payload = array_merge([
                'status' => $enrollmentStatus,
            ], $attrs);

            if (isset($payload['notes']) && !empty($enrollment->notes)) {
                $payload['notes'] = trim($enrollment->notes."\n".$payload['notes']);
            }

            if (isset($payload['meta']) && is_array($payload['meta'])) {
                $payload['meta'] = array_merge($enrollment->meta ?? [], $payload['meta']);
            }

            $enrollment->update($payload);
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
            throw ValidationException::withMessages([
                'status' => "Cannot change status from '{$student->status}' to '{$newStatus}'.",
            ]);
        }

        if ($student->status === $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Student is already '{$newStatus}'.",
            ]);
        }
    }
}
