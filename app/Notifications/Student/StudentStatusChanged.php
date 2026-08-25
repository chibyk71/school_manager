<?php

namespace App\Notifications\Student;

use App\Events\Student\StudentStatusChanged as StudentStatusChangedEvent;
use App\Models\Academic\Student;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * StudentStatusChanged Notification (v2.0 – Production-Ready)
 *
 * Sent to guardians when a student's status is changed (e.g. suspended, withdrawn, graduated, reinstated, etc.).
 *
 * This notification keeps parents informed of important changes in their child's academic status
 * with clear explanation and next steps where appropriate.
 *
 * Features / Problems Solved:
 * - Clear, empathetic communication for sensitive status changes.
 * - Includes old and new status with reason and effective date.
 * - Provides relevant next steps depending on the new status.
 * - Queuable for performance.
 * - Stores useful metadata for database notifications and audit.
 *
 * Fits into the Student Management Module:
 * - Triggered by a listener listening to the StudentStatusChanged event.
 * - Sent to all guardians linked to the student (especially primary contact).
 */

class StudentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public Student $student;
    public School $school;
    public string $oldStatus;
    public string $newStatus;
    public ?string $reason;
    public \Carbon\Carbon $changeDate;
    public ?\Carbon\Carbon $until;

    /**
     * Create a new notification instance.
     */
    public function __construct(StudentStatusChangedEvent $event)
    {
        $this->student = $event->student;
        $this->school = $event->school;
        $this->oldStatus = $event->oldStatus;
        $this->newStatus = $event->newStatus;
        $this->reason = $event->reason;
        $this->changeDate = $event->changeDate;
        $this->until = $event->until;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = match ($this->newStatus) {
            'suspended' => 'has been temporarily suspended',
            'withdrawn' => 'has been withdrawn from the school',
            'transferred' => 'has been transferred to another school',
            'graduated' => 'has successfully graduated',
            'deceased' => 'has been marked as deceased',
            'active' => 'has been reinstated and is now active',
            default => "status has been changed to {$this->newStatus}",
        };

        $message = (new MailMessage)
            ->subject("Important Update: {$this->student->full_name} - Status Change")
            ->greeting('Dear ' . ($notifiable->profile?->full_name ?? $notifiable->name) . ',');

        $message->line("This is to inform you that **{$this->student->full_name}** {$statusLabel} at {$this->school->name}.")
            ->line("**Previous Status:** " . ucfirst($this->oldStatus))
            ->line("**New Status:** " . ucfirst($this->newStatus))
            ->line("**Effective Date:** " . $this->changeDate->format('d F Y'));

        if ($this->reason) {
            $message->line("**Reason:** {$this->reason}");
        }

        if ($this->until) {
            $message->line("**Until:** " . $this->until->format('d F Y'));
        }

        // Add next steps based on new status
        if ($this->newStatus === 'suspended') {
            $message->line('During this period, the student will not attend classes. We will notify you when reinstatement is possible.');
        } elseif ($this->newStatus === 'withdrawn' || $this->newStatus === 'transferred') {
            $message->line('Please contact the school office to complete any necessary clearance procedures.');
        } elseif ($this->newStatus === 'graduated') {
            $message->line('Congratulations on this achievement! Details regarding graduation ceremony and certificates will be shared soon.');
        }

        $message->action('View Student Profile', route('admin.students.show', $this->student))
            ->line('Thank you for your continued partnership with us.')
            ->salutation($this->school->name . ' Student Affairs Team');

        return $message;
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_status_changed',
            'student_id' => $this->student->id,
            'student_name' => $this->student->full_name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'reason' => $this->reason,
            'change_date' => $this->changeDate->toIso8601String(),
            'until' => $this->until?->toIso8601String(),
            'school_id' => $this->school->id,
            'action_url' => route('admin.students.show', $this->student),
        ];
    }
}
