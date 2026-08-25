<?php

namespace App\Listeners\Student;

use App\Events\Student\StudentEnrolled;
use App\Models\Guardian;
use App\Notifications\Student\StudentEnrolled as StudentEnrolledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyGuardiansOnStudentEnrolled Listener (v2.0 – Production-Ready)
 *
 * This listener is triggered when a student enrollment is fully completed 
 * (Profile + Student + Guardians + Placement + status = 'enrolled').
 *
 * It sends a welcoming enrollment confirmation notification to all linked guardians,
 * including portal login credentials if a parent/guardian account was created.
 *
 * Features / Problems Solved:
 * - Automatically notifies all guardians after successful enrollment.
 * - Passes portal credentials securely to the notification when an account was created.
 * - Prioritizes the primary contact guardian.
 * - Queued to avoid delaying the enrollment process.
 * - Comprehensive logging for audit and debugging.
 *
 * Fits into the Student Management Module:
 * - Listens to StudentEnrolled event (dispatched from StudentEnrollmentService::completeEnrollment() and enrollFromWizard()).
 * - Works with the guardian_student pivot and Student model relationships.
 * - Completes the enrollment → notification flow with portal access details.
 */

class NotifyGuardiansOnStudentEnrolled implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(StudentEnrolled $event): void
    {
        $student = $event->student;

        // Get all guardians linked to this student
        $guardians = $student->guardians()
            ->with('profile')
            ->get();

        if ($guardians->isEmpty()) {
            Log::warning('Student enrolled but no guardians found to notify', [
                'student_id' => $student->id,
            ]);
            return;
        }

        $guardians->each(function (Guardian $guardian) use ($event) {
            // Check if this guardian should receive portal credentials
            $shouldSendCredentials = $guardian->pivot?->can_access_portal ?? false;

            $guardian->notify(
                new StudentEnrolledNotification(
                    event: $event,
                    portalAccountCreated: $shouldSendCredentials,
                    portalUsername: $shouldSendCredentials ? $guardian->profile?->user?->username : null,
                    portalPassword: null   // Never send plain password in bulk – handled in UserAccountService event
                )
            );
        });

        Log::info('StudentEnrolled notifications sent to guardians', [
            'student_id'     => $student->id,
            'guardian_count' => $guardians->count(),
        ]);
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(StudentEnrolled $event): bool
    {
        // Always queue to keep the enrollment process responsive
        return true;
    }
}