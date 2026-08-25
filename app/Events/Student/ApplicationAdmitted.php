<?php

namespace App\Events\Student;

use App\Models\Student\Student;
use App\Models\Student\StudentApplication;
use App\Models\School;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ApplicationAdmitted Event (v2.0 – Production-Ready)
 *
 * Fired when a pending StudentApplication is successfully admitted and converted
 * into a Profile + Student record (including initial placement and guardians).
 *
 * This event enables decoupled handling of:
 *   - Notifications to the applicant/guardians (welcome, next steps)
 *   - Notifications to admins/teachers (new student admitted)
 *   - Audit logging and compliance records
 *   - Real-time updates on admin dashboards
 *   - Any post-admission workflows (fee setup, document requests, etc.)
 *
 * Features / Problems Solved:
 * - Provides full context: the original application, the newly created student, the profile, and the admin who admitted.
 * - Clearly separates admission success from the enrollment completion step.
 * - Supports broadcasting for real-time admin interface updates.
 * - Consistent naming and structure with other student events (ApplicationSubmitted, StudentEnrolled, etc.).
 *
 * Fits into the Student Management Module:
 * - Dispatched from StudentApplicationService::admitApplication().
 * - Listened to by notification listeners, audit listeners, and real-time dashboard components.
 * - Used in frontend for instant feedback after clicking "Admit" in Applications/Show.vue.
 */

class ApplicationAdmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The original application that was admitted.
     */
    public StudentApplication $application;

    /**
     * The newly created Student record.
     */
    public Student $student;

    /**
     * The central Profile created/used for this student.
     */
    public \App\Models\Profile $profile;

    /**
     * The school the student was admitted into.
     */
    public School $school;

    /**
     * The admin/user who performed the admission.
     */
    public User $admittedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        StudentApplication $application,
        Student $student,
        \App\Models\Profile $profile,
        School $school,
        User $admittedBy
    ) {
        $this->application = $application;
        $this->student = $student;
        $this->profile = $profile;
        $this->school = $school;
        $this->admittedBy = $admittedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to the school's private channel for real-time updates
        return [
            'private-school.' . $this->school->id,
        ];
    }

    /**
     * The name of the broadcast event.
     */
    public function broadcastAs(): string
    {
        return 'application.admitted';
    }
}