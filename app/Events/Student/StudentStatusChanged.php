<?php

namespace App\Events\Student;

use App\Models\Student\Student;
use App\Models\School;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * StudentStatusChanged Event (v2.0 – Production-Ready)
 *
 * Fired whenever a student's status is changed through StudentStatusService
 * (activate, suspend, reinstate, withdraw, graduate, markDeceased, transferOut, etc.).
 *
 * This is the most frequently fired student event and serves as the central
 * audit/notification point for all lifecycle changes.
 *
 * Features / Problems Solved:
 * - Provides complete context for any status transition (old status, new status, reason, dates).
 * - Decouples notification, audit logging, and side-effect logic from the service layer.
 * - Supports broadcasting for real-time admin/teacher dashboard updates.
 * - Consistent naming and structure with other student events.
 * - Includes the acting user for accountability.
 *
 * Fits into the Student Management Module:
 * - Dispatched from StudentStatusService on every status change.
 * - Listened to by:
 *     • Notification listeners (SMS/email to guardians)
 *     • Audit logging
 *     • Real-time dashboard updates
 *     • Fee/attendance/reporting adjustments
 * - Used in frontend for instant status badge updates and activity feeds.
 */

class StudentStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The student whose status was changed.
     */
    public Student $student;

    /**
     * The school the student belongs to.
     */
    public School $school;

    /**
     * The user who performed the status change.
     */
    public User $changedBy;

    /**
     * Previous status before the change.
     */
    public string $oldStatus;

    /**
     * New status after the change.
     */
    public string $newStatus;

    /**
     * Reason provided for the status change (if any).
     */
    public ?string $reason;

    /**
     * Date the status change took effect.
     */
    public \Carbon\Carbon $changeDate;

    /**
     * For temporary statuses (e.g. suspension), when it ends.
     */
    public ?\Carbon\Carbon $until;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Student $student,
        School $school,
        User $changedBy,
        string $oldStatus,
        string $newStatus,
        ?string $reason = null,
        ?\Carbon\Carbon $until = null
    ) {
        $this->student = $student;
        $this->school = $school;
        $this->changedBy = $changedBy;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->reason = $reason;
        $this->changeDate = now();
        $this->until = $until;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // School-wide channel for real-time updates
            'private-school.' . $this->school->id,
        ];
    }

    /**
     * The name of the broadcast event.
     */
    public function broadcastAs(): string
    {
        return 'student.status_changed';
    }

    /**
     * Optional: Human-readable description for logs/audit
     */
    public function getDescription(): string
    {
        $action = match ($this->newStatus) {
            'active' => 'activated',
            'suspended' => 'suspended',
            'withdrawn' => 'withdrawn',
            'transferred' => 'transferred',
            'graduated' => 'graduated',
            'deceased' => 'marked as deceased',
            default => "changed status to {$this->newStatus}",
        };

        return "Student {$this->student->full_name} was {$action} by {$this->changedBy->name}"
            . ($this->reason ? " (Reason: {$this->reason})" : '');
    }
}