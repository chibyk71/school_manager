<?php

namespace App\Listeners\Student;

use App\Events\Student\StudentStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * LogStudentStatusChange Listener (v2.0 – Production-Ready)
 *
 * This listener creates a detailed audit log entry whenever a student's status is changed
 * through the StudentStatusService.
 *
 * It logs:
 * - The student and their current details
 * - Old and new status
 * - Reason (if provided)
 * - Effective dates
 * - Who performed the change
 *
 * Features / Problems Solved:
 * - Provides a complete, searchable audit trail for all status changes.
 * - Helps with compliance, dispute resolution, and administrative oversight.
 * - Uses structured logging (with context) for easy filtering in production.
 * - Queued to avoid delaying status change operations.
 * - Non-intrusive — does not affect the main business flow.
 *
 * Fits into the Student Management Module:
 * - Listens to StudentStatusChanged event (dispatched from StudentStatusService).
 * - Complements the notification system and provides the "audit" side of status changes.
 */

class LogStudentStatusChange implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(StudentStatusChanged $event): void
    {
        $student = $event->student;

        Log::channel('audit')->info('Student status changed', [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'admission_number' => $student->admission_number,
            'old_status' => $event->oldStatus,
            'new_status' => $event->newStatus,
            'reason' => $event->reason,
            'change_date' => $event->changeDate->toDateTimeString(),
            'status_until' => $event->until?->toDateTimeString(),
            'changed_by_user_id' => $event->changedBy->id,
            'changed_by_name' => $event->changedBy->name,
            'school_id' => $event->school->id,
            'school_name' => $event->school->name,
            'current_placement' => $student->currentPlacement?->getDisplayNameAttribute(),
        ]);

        // Optional: Also log to a more structured activity log if you use Spatie Activity Log
        activity()->performedOn($student)
            ->causedBy($event->changedBy)
            ->withProperties([
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'reason' => $event->reason,
            ])
            ->log("Student status changed from {$event->oldStatus} to {$event->newStatus}");
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(StudentStatusChanged $event): bool
    {
        // Always queue audit logging to keep the main status change fast
        return true;
    }
}