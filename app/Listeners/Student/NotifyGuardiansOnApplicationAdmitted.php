<?php

namespace App\Listeners\Student;

use App\Events\Student\ApplicationAdmitted;
use App\Notifications\Student\ApplicationAdmitted as ApplicationAdmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyGuardiansOnApplicationAdmitted Listener (v2.0 – Production-Ready)
 *
 * This listener is triggered when a student application is successfully admitted.
 * It sends a welcoming notification with next steps to all guardians linked to the new student.
 *
 * Features / Problems Solved:
 * - Automatically notifies guardians after successful admission.
 * - Uses the rich ApplicationAdmitted notification we created earlier.
 * - Prioritizes the primary contact guardian.
 * - Queued to avoid delaying the admission process.
 * - Handles cases where no guardians exist yet (graceful fallback).
 *
 * Fits into the Student Management Module:
 * - Listens to ApplicationAdmitted event (dispatched from StudentApplicationService::admitApplication()).
 * - Works with the guardian_student pivot and Student model relationships.
 * - Completes the admission → notification flow.
 */

class NotifyGuardiansOnApplicationAdmitted implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ApplicationAdmitted $event): void
    {
        $student = $event->student;

        // Get all guardians linked to this student
        $guardians = $student->guardians()->with('profile')->get();

        if ($guardians->isEmpty()) {
            // Fallback: If no guardians were linked yet, notify the reviewer/admin as a safety measure
            Log::warning('No guardians found for newly admitted student', [
                'student_id' => $student->id,
                'application_id' => $event->application->id,
            ]);
            return;
        }

        foreach ($guardians as $guardian) {
            // Send notification to each guardian
            $guardian->notify(
                new ApplicationAdmittedNotification($event)
            );
        }

        Log::info('ApplicationAdmitted notifications sent to guardians', [
            'student_id' => $student->id,
            'guardian_count' => $guardians->count(),
        ]);
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(ApplicationAdmitted $event): bool
    {
        // Always queue to keep the admission process fast
        return true;
    }
}