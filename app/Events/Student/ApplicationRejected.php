<?php

namespace App\Events\Student;

use App\Models\Student\StudentApplication;
use App\Models\School;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ApplicationRejected Event (v2.0 – Production-Ready)
 *
 * Fired when a pending StudentApplication is rejected by an admin.
 *
 * This event enables decoupled handling of:
 *   - Notifications to the applicant/guardians (rejection reason + next steps)
 *   - Audit logging and compliance records
 *   - Real-time updates on admin dashboards (e.g., application counters)
 *   - Any post-rejection workflows (archive, analytics, etc.)
 *
 * Features / Problems Solved:
 * - Provides full context: the application, school, reviewer, and rejection reason.
 * - Keeps rejection logic separate from the main service for better maintainability.
 * - Supports broadcasting for real-time admin interface updates.
 * - Consistent naming and structure with other student events (ApplicationSubmitted, ApplicationAdmitted, etc.).
 *
 * Fits into the Student Management Module:
 * - Dispatched from StudentApplicationService::rejectApplication().
 * - Listened to by notification listeners, audit listeners, and real-time dashboard components.
 * - Used in frontend for instant feedback after clicking "Reject" in Applications/Show.vue.
 */

class ApplicationRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The student application that was rejected.
     */
    public StudentApplication $application;

    /**
     * The school the application belonged to.
     */
    public School $school;

    /**
     * The admin/user who rejected the application.
     */
    public User $rejectedBy;

    /**
     * The reason provided for rejection.
     */
    public string $rejectionReason;

    /**
     * Create a new event instance.
     */
    public function __construct(
        StudentApplication $application,
        School $school,
        User $rejectedBy,
        string $rejectionReason
    ) {
        $this->application = $application;
        $this->school = $school;
        $this->rejectedBy = $rejectedBy;
        $this->rejectionReason = $rejectionReason;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to the school's private channel for real-time admin updates
        return [
            'private-school.' . $this->school->id,
        ];
    }

    /**
     * The name of the broadcast event.
     */
    public function broadcastAs(): string
    {
        return 'application.rejected';
    }
}