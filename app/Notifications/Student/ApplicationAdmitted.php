<?php

namespace App\Notifications\Student;

use App\Events\Student\ApplicationAdmitted as ApplicationAdmittedEvent;
use App\Models\Student\StudentApplication;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ApplicationAdmitted Notification (v2.0 – Production-Ready)
 *
 * Sent to the guardians (and optionally the applicant) when a student application 
 * has been successfully admitted and a Student record has been created.
 *
 * This is a positive, welcoming notification that informs the family of the next steps.
 *
 * Features / Problems Solved:
 * - Warm, professional tone suitable for parents/guardians.
 * - Includes clear next steps (what happens after admission).
 * - Links to the student profile or portal (if applicable).
 * - Queuable for performance.
 * - Personalized with student name and application number.
 *
 * Fits into the Student Management Module:
 * - Triggered by a listener listening to the ApplicationAdmitted event.
 * - Sent primarily to guardians linked to the new student.
 */

class ApplicationAdmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public StudentApplication $application;
    public \App\Models\Student\Student $student;
    public \App\Models\Profile $profile;
    public School $school;

    /**
     * Create a new notification instance.
     */
    public function __construct(ApplicationAdmittedEvent $event)
    {
        $this->application = $event->application;
        $this->student = $event->student;
        $this->profile = $event->profile;
        $this->school = $event->school;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];   // Can add SMS later via custom channel
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Congratulations! {$this->profile->full_name} has been Admitted")
            ->greeting('Dear ' . $notifiable->profile?->full_name ?? $notifiable->name . ',')
            ->line("We are pleased to inform you that **{$this->profile->full_name}** has been officially admitted to {$this->school->name}.")
            ->line("**Application Number:** {$this->application->application_number}")
            ->line("**Admission Date:** " . now()->format('d F Y'))
            ->line('Next Steps:')
            ->line('• Complete any remaining documentation')
            ->line('• Pay the admission/acceptance fee (if applicable)')
            ->line('• Attend orientation / receive class placement details')
            ->action('View Student Profile', route('admin.students.show', $this->student))
            ->line('We look forward to welcoming your child to our school community!')
            ->salutation('Best regards,')
            ->salutation($this->school->name . ' Admissions Team');
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_admitted',
            'application_id' => $this->application->id,
            'student_id' => $this->student->id,
            'student_name' => $this->profile->full_name,
            'application_number' => $this->application->application_number,
            'school_id' => $this->school->id,
            'admitted_at' => now()->toIso8601String(),
            'action_url' => route('admin.students.show', $this->student),
        ];
    }
}