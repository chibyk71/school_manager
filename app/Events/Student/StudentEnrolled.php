<?php

namespace App\Events\Student;

use App\Models\Student\Student;
use App\Models\School;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * StudentEnrolled Event (v2.0 – Production-Ready)
 *
 * Fired when a student enrollment is fully completed — i.e., after the student has:
 *   - A Profile
 *   - A Student record
 *   - At least one guardian
 *   - An initial academic placement
 *   - Status changed from 'admitted' to 'enrolled'
 *
 * This is the final success event in the enrollment/admission flow.
 *
 * Features / Problems Solved:
 * - Provides complete context after successful enrollment (student, profile, school, enroller).
 * - Decouples notification, audit, and post-enrollment workflows from the service layer.
 * - Supports real-time updates (broadcasting) for admin dashboards.
 * - Consistent naming and structure with other student events.
 *
 * Fits into the Student Management Module:
 * - Dispatched from StudentEnrollmentService::completeEnrollment() and enrollFromWizard().
 * - Listened to by:
 *     • Notification listeners (welcome email/SMS to guardians)
 *     • Audit listeners
 *     • Real-time dashboard updates
 *     • Any post-enrollment automation (fee generation, ID card, portal setup, etc.)
 * - Used in frontend for success toasts and real-time student count updates.
 */

class StudentEnrolled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The newly enrolled student.
     */
    public Student $student;

    /**
     * The central profile linked to this student.
     */
    public \App\Models\Profile $profile;

    /**
     * The school where the student was enrolled.
     */
    public School $school;

    /**
     * The user (admin/staff) who performed the enrollment.
     */
    public User $enrolledBy;

    /**
     * Whether this enrollment came from an application or direct wizard.
     */
    public bool $fromApplication;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Student $student,
        \App\Models\Profile $profile,
        School $school,
        User $enrolledBy,
        bool $fromApplication = false
    ) {
        $this->student = $student;
        $this->profile = $profile;
        $this->school = $school;
        $this->enrolledBy = $enrolledBy;
        $this->fromApplication = $fromApplication;
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
        return 'student.enrolled';
    }
}