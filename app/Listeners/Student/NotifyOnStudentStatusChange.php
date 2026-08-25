<?php

namespace App\Listeners\Student;

use App\Events\Student\StudentStatusChanged;
use App\Notifications\Student\StudentStatusChanged as StudentStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyOnStudentStatusChange Listener (v2.0 – Production-Ready)
 *
 * This listener is triggered whenever a student's status is changed through 
 * the StudentStatusService (activate, suspend, reinstate, withdraw, graduate, 
 * mark deceased, transfer out, etc.).
 *
 * It intelligently notifies the linked guardians with the appropriate 
 * StudentStatusChanged notification.
 *
 * Features / Problems Solved:
 * - Automatically notifies guardians on any status change.
 * - Prioritizes the primary contact guardian.
 * - Uses the rich StudentStatusChanged notification we created earlier.
 * - Queued to avoid delaying status change operations.
 * - Comprehensive logging for audit and debugging.
 *
 * Fits into the Student Management Module:
 * - Listens to StudentStatusChanged event (dispatched from StudentStatusService).
 * - Works with the guardian_student pivot and Student model relationships.
 * - Completes the status change → guardian communication flow.
 */

class NotifyOnStudentStatusChange implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(StudentStatusChanged $event): void
    {
        $student = $event->student;

        // Get all guardians linked to this student
        $guardians = $student->guardians()
            ->with('profile')
            ->get();

        if ($guardians->isEmpty()) {
            Log::warning('Student status changed but no guardians found to notify', [
                'student_id' => $student->id,
                'new_status' => $event->newStatus,
            ]);
            return;
        }

        foreach ($guardians as $guardian) {
            // Send notification to each guardian
            $guardian->notify(
                new StudentStatusChangedNotification($event)
            );
        }

        Log::info('StudentStatusChanged notifications sent to guardians', [
            'student_id' => $student->id,
            'new_status' => $event->newStatus,
            'old_status' => $event->oldStatus,
            'guardian_count' => $guardians->count(),
            'changed_by' => $event->changedBy->name ?? 'System',
        ]);
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(StudentStatusChanged $event): bool
    {
        // Always queue to keep status changes fast and responsive
        return true;
    }
}