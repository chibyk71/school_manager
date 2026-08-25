<?php

namespace App\Notifications\Student;

use App\Events\Student\StudentTransferred as StudentTransferredEvent;
use App\Models\Academic\Student;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * StudentTransferred Notification (v2.0 – Production-Ready)
 *
 * Sent to guardians when a student is transferred:
 *   - Internal transfer: to another school within the same tenant
 *   - External transfer: leaving the entire tenant
 *
 * This notification informs parents about the transfer, provides context, and 
 * includes a link to download/generate the transfer certificate when applicable.
 *
 * Features / Problems Solved:
 * - Handles both internal and external transfers with appropriate messaging.
 * - Clear explanation of what the transfer means.
 * - Includes transfer reason and effective date.
 * - Provides direct access to transfer certificate (for internal transfers).
 * - Professional and empathetic tone.
 * - Queuable for performance.
 *
 * Fits into the Student Management Module:
 * - Triggered by a listener listening to the StudentTransferred event.
 * - Sent to all guardians linked to the student.
 */

class StudentTransferred extends Notification implements ShouldQueue
{
    use Queueable;

    public Student $oldStudent;
    public ?Student $newStudent;
    public School $sourceSchool;
    public ?School $targetSchool;
    public string $reason;
    public bool $isInternalTransfer;

    /**
     * Create a new notification instance.
     */
    public function __construct(StudentTransferredEvent $event)
    {
        $this->oldStudent         = $event->oldStudent;
        $this->newStudent         = $event->newStudent;
        $this->sourceSchool       = $event->sourceSchool;
        $this->targetSchool       = $event->targetSchool;
        $this->reason             = $event->reason;
        $this->isInternalTransfer = $event->isInternalTransfer;
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
        $studentName = $this->oldStudent->full_name;

        if ($this->isInternalTransfer && $this->targetSchool) {
            $subject = "Transfer Update: {$studentName} has been transferred to {$this->targetSchool->name}";
            
            $message = (new MailMessage)
                ->subject($subject)
                ->greeting('Dear ' . ($notifiable->profile?->full_name ?? $notifiable->name) . ',')
                ->line("This is to inform you that **{$studentName}** has been officially transferred from {$this->sourceSchool->name} to **{$this->targetSchool->name}**.")
                ->line("**Transfer Reason:** {$this->reason}")
                ->line("**Effective Date:** " . now()->format('d F Y'))
                ->line('The new school will contact you shortly regarding admission formalities, class placement, and orientation.')
                ->action('View New Student Record', route('admin.students.show', $this->newStudent))
                ->line('We thank you for your trust in our institution and wish your child continued success in their new school.');
        } else {
            // External transfer
            $subject = "Transfer Update: {$studentName} has been transferred";
            
            $destination = $this->targetSchool?->name ?? $this->reason;
            $message = (new MailMessage)
                ->subject($subject)
                ->greeting('Dear ' . ($notifiable->profile?->full_name ?? $notifiable->name) . ',')
                ->line("This is to inform you that **{$studentName}** has been transferred out of {$this->sourceSchool->name}.")
                ->line("**Destination:** {$destination}")
                ->line("**Reason:** {$this->reason}")
                ->line("**Effective Date:** " . now()->format('d F Y'))
                ->action('Download Transfer Certificate', route('admin.students.transfer-certificate', $this->oldStudent))
                ->line('Please contact the admissions office if you need any assistance with the transfer process or documentation.');
        }

        $message->salutation($this->sourceSchool->name . ' Student Affairs Team');

        return $message;
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'                  => 'student_transferred',
            'student_id'            => $this->oldStudent->id,
            'student_name'          => $this->oldStudent->full_name,
            'old_school_id'         => $this->sourceSchool->id,
            'new_school_id'         => $this->targetSchool?->id,
            'is_internal'           => $this->isInternalTransfer,
            'reason'                => $this->reason,
            'transferred_at'        => now()->toIso8601String(),
            'action_url'            => $this->isInternalTransfer && $this->newStudent
                ? route('admin.students.show', $this->newStudent)
                : route('admin.students.transfer-certificate', $this->oldStudent),
        ];
    }
}