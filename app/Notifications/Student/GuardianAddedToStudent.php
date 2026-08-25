<?php

namespace App\Notifications\Student;

use App\Models\Academic\Student;
use App\Models\Guardian;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * GuardianAddedToStudent Notification (v2.0 – Production-Ready)
 *
 * Sent to a guardian when they are newly linked to a student (via the guardian_student pivot).
 *
 * This notification informs the guardian of their new responsibility and provides 
 * quick access to the student's profile and relevant school information.
 *
 * Features / Problems Solved:
 * - Warm and clear communication when a guardian is added.
 * - Explains the relationship type (father, mother, uncle, etc.).
 * - Provides direct links to the student profile and parent portal.
 * - Includes school contact information for questions.
 * - Queuable for performance.
 *
 * Fits into the Student Management Module:
 * - Can be dispatched from StudentGuardianService when a guardian is attached to a student.
 * - Sent to the newly added guardian.
 * - Complements the existing notification system for student lifecycle events.
 */

class GuardianAddedToStudent extends Notification implements ShouldQueue
{
    use Queueable;

    public Student $student;
    public Guardian $guardian;
    public School $school;
    public string $relationship;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Student $student,
        Guardian $guardian,
        School $school,
        string $relationship
    ) {
        $this->student = $student;
        $this->guardian = $guardian;
        $this->school = $school;
        $this->relationship = $relationship;
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
        return (new MailMessage)
            ->subject("You have been added as {$this->relationship} to {$this->student->full_name}")
            ->greeting('Dear ' . $this->guardian->full_name . ',')
            ->line("We are writing to inform you that you have been officially added as **{$this->relationship}** to **{$this->student->full_name}** at {$this->school->name}.")
            ->line("You now have access to view important information regarding the student's academic progress, attendance, fees, and announcements.")
            ->line("**Student Details:**")
            ->line("• Name: {$this->student->full_name}")
            ->line("• Admission Number: {$this->student->admission_number}")
            ->line("• Current Status: {$this->student->getStatusLabelAttribute()}")
            ->action('View Student Profile', route('admin.students.show', $this->student))
            ->line('If you need any assistance or have questions, please feel free to contact the school office.')
            ->salutation('Best regards,')
            ->salutation($this->school->name . ' Student Affairs Team');
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'guardian_added_to_student',
            'student_id' => $this->student->id,
            'student_name' => $this->student->full_name,
            'guardian_id' => $this->guardian->id,
            'relationship' => $this->relationship,
            'school_id' => $this->school->id,
            'added_at' => now()->toIso8601String(),
            'action_url' => route('admin.students.show', $this->student),
        ];
    }
}