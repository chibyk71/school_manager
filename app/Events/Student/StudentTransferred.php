<?php

namespace App\Events\Student;

use App\Models\Student\Student;
use App\Models\School;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * StudentTransferred Event (v2.0 – Production-Ready)
 *
 * Fired when a student is transferred:
 *   1. Internally within the tenant → old record marked 'transferred' + new Student record created in target school
 *   2. Externally out of the tenant  → old record marked 'transferred'
 *
 * This event enables decoupled handling of:
 *   - Notifications to guardians (transfer confirmation, next steps)
 *   - Audit logging and compliance records
 *   - Transfer certificate generation workflow
 *   - Real-time updates on admin dashboards
 *
 * Features / Problems Solved:
 * - Clearly distinguishes between internal and external transfers.
 * - Provides both old and new student records (when internal).
 * - Includes full context (source school, target school, reason, transferredBy).
 * - Supports broadcasting for live admin interface updates.
 * - Consistent naming and structure with other student events.
 *
 * Fits into the Student Management Module:
 * - Dispatched from StudentTransferService::transferWithinTenant() and transferOut().
 * - Listened to by notification listeners, audit listeners, and certificate generation jobs.
 * - Used in frontend for success feedback after transfer action.
 */

class StudentTransferred
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The original (source) student record that was marked as transferred.
     */
    public Student $oldStudent;

    /**
     * The new student record in the target school (only present for internal transfers).
     */
    public ?Student $newStudent;

    /**
     * The source school the student is leaving.
     */
    public School $sourceSchool;

    /**
     * The target school (only for internal transfers).
     */
    public ?School $targetSchool;

    /**
     * The user who performed the transfer.
     */
    public User $transferredBy;

    /**
     * The reason for the transfer.
     */
    public string $reason;

    /**
     * Whether this is an internal transfer within the tenant.
     */
    public bool $isInternalTransfer;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Student $oldStudent,
        ?Student $newStudent,
        School $sourceSchool,
        ?School $targetSchool,
        User $transferredBy,
        string $reason
    ) {
        $this->oldStudent = $oldStudent;
        $this->newStudent = $newStudent;
        $this->sourceSchool = $sourceSchool;
        $this->targetSchool = $targetSchool;
        $this->transferredBy = $transferredBy;
        $this->reason = $reason;
        $this->isInternalTransfer = $newStudent !== null;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Always broadcast to the source school
        $channels = ['private-school.' . $this->sourceSchool->id];

        // Also broadcast to target school for internal transfers
        if ($this->isInternalTransfer && $this->targetSchool) {
            $channels[] = 'private-school.' . $this->targetSchool->id;
        }

        return $channels;
    }

    /**
     * The name of the broadcast event.
     */
    public function broadcastAs(): string
    {
        return 'student.transferred';
    }
}