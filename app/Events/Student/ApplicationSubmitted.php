<?php

namespace App\Events\Student;

use App\Models\Student\StudentApplication;
use App\Models\School;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ApplicationSubmitted Event (v2.0 – Production-Ready)
 *
 * Fired whenever a new student application is submitted, whether through:
 *   - The public application portal
 *   - Admin direct creation
 *
 * This event enables decoupled handling of:
 *   - Notifications (email/SMS to admin or applicant)
 *   - Audit logging
 *   - Webhooks (if needed for integrations)
 *   - Real-time updates (via broadcasting)
 *
 * Features / Problems Solved:
 * - Decouples notification and side-effect logic from StudentApplicationService.
 * - Provides rich context (application, school, submitter) for listeners.
 * - Supports both public and admin submissions.
 * - Uses Laravel's event broadcasting capabilities (can be queued).
 * - Consistent naming and structure with other student events (StudentEnrolled, ApplicationAdmitted, etc.).
 *
 * Fits into the Student Management Module:
 * - Dispatched from StudentApplicationService::submitPublicApplication() and submitAdminApplication().
 * - Listened to by Notification listeners, Audit listeners, or real-time dashboard updates.
 * - Used in frontend for real-time application counters or toast notifications (if broadcasted).
 */

class ApplicationSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The student application that was submitted.
     */
    public StudentApplication $application;

    /**
     * The school the application belongs to.
     */
    public School $school;

    /**
     * The user who submitted the application (null for public portal submissions).
     */
    public ?User $submittedBy;

    /**
     * Whether this was submitted via public portal.
     */
    public bool $isPublicSubmission;

    /**
     * Create a new event instance.
     */
    public function __construct(
        StudentApplication $application,
        School $school,
        ?User $submittedBy = null
    ) {
        $this->application = $application;
        $this->school = $school;
        $this->submittedBy = $submittedBy;
        $this->isPublicSubmission = $submittedBy === null;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to school-specific channel for real-time admin dashboard updates
        return [
            'private-school.' . $this->school->id,
        ];
    }

    /**
     * The name of the broadcast event.
     */
    public function broadcastAs(): string
    {
        return 'application.submitted';
    }
}