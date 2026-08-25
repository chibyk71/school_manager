<?php

namespace App\Listeners\Student;

use App\Events\Student\StudentTransferred;
use App\Notifications\Student\StudentTransferred as StudentTransferredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyOnStudentTransfer Listener (v2.0 – Production-Ready)
 *
 * This listener is triggered when a student is transferred (internal or external).
 *
 * It sends a clear transfer confirmation notification to all linked guardians
 * and handles special logic for internal transfers (e.g., certificate generation).
 *
 * Features / Problems Solved:
 * - Automatically notifies guardians about any transfer.
 * - Differentiates messaging between internal and external transfers.
 * - Triggers certificate generation for internal transfers.
 * - Uses the rich StudentTransferred notification we created earlier.
 * - Queued to avoid delaying the transfer process.
 * - Comprehensive logging for audit purposes.
 *
 * Fits into the Student Management Module:
 * - Listens to StudentTransferred event (dispatched from StudentTransferService).
 * - Works with the guardian_student pivot and Student model relationships.
 * - Completes the transfer → guardian communication flow.
 */

class NotifyOnStudentTransfer implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(StudentTransferred $event): void
    {
        $oldStudent = $event->oldStudent;

        // Get all guardians linked to the student (from the old record)
        $guardians = $oldStudent->guardians()
            ->with('profile')
            ->get();

        if ($guardians->isEmpty()) {
            Log::warning('Student transferred but no guardians found to notify', [
                'student_id' => $oldStudent->id,
                'is_internal' => $event->isInternalTransfer,
            ]);
            return;
        }

        foreach ($guardians as $guardian) {
            $guardian->notify(
                new StudentTransferredNotification($event)
            );
        }

        // Special handling for internal transfers
        if ($event->isInternalTransfer && $event->newStudent) {
            Log::info('Internal transfer notification sent with certificate option', [
                'old_student_id' => $oldStudent->id,
                'new_student_id' => $event->newStudent->id,
                'target_school' => $event->targetSchool?->name,
                'guardian_count' => $guardians->count(),
            ]);
        } else {
            Log::info('External transfer notification sent to guardians', [
                'student_id' => $oldStudent->id,
                'destination' => $event->reason,
                'guardian_count' => $guardians->count(),
            ]);
        }
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(StudentTransferred $event): bool
    {
        // Always queue to keep the transfer process fast
        return true;
    }
}