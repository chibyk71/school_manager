<?php

namespace App\Notifications\Student;

use App\Events\Student\StudentEnrolled as StudentEnrolledEvent;
use App\Models\Student\Student;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * StudentEnrolled Notification (v2.0 – Production-Ready)
 *
 * Sent to the guardians when a student has been fully enrolled — i.e., after:
 *   - Profile and Student records are created
 *   - Guardians are linked
 *   - Initial academic placement is assigned
 *   - Status is updated to 'enrolled'
 *
 * This is the main "welcome" notification that informs parents the admission process is complete
 * and provides next steps + portal login information (if a user account was created).
 *
 * Features / Problems Solved:
 * - Warm, welcoming tone suitable for parents/guardians.
 * - Clearly lists next steps after enrollment.
 * - Includes portal login credentials if an account was created for the guardian.
 * - Queuable for performance.
 * - Stores useful metadata for database notifications.
 *
 * Fits into the Student Management Module:
 * - Triggered by a listener listening to the StudentEnrolled event.
 * - Sent to all guardians linked to the newly enrolled student.
 */

class StudentEnrolled extends Notification implements ShouldQueue
{
    use Queueable;

    public Student $student;
    public \App\Models\Profile $profile;
    public School $school;
    public bool $portalAccountCreated;
    public ?string $portalUsername;
    public ?string $portalPassword;   // Only passed if newly generated

    /**
     * Create a new notification instance.
     */
    public function __construct(
        StudentEnrolledEvent $event,
        bool $portalAccountCreated = false,
        ?string $portalUsername = null,
        ?string $portalPassword = null
    ) {
        $this->student              = $event->student;
        $this->profile              = $event->profile;
        $this->school               = $event->school;
        $this->portalAccountCreated = $portalAccountCreated;
        $this->portalUsername       = $portalUsername;
        $this->portalPassword       = $portalPassword;
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
        $message = (new MailMessage)
            ->subject("Welcome! {$this->profile->full_name} has been Enrolled at {$this->school->name}")
            ->greeting('Dear ' . ($notifiable->profile?->full_name ?? $notifiable->name) . ',');

        $message->line("We are delighted to inform you that **{$this->profile->full_name}** has been successfully enrolled at {$this->school->name}.")
                ->line("**Admission Number:** {$this->student->admission_number}")
                ->line("**Class Level:** " . ($this->student->currentPlacement?->classLevel?->name ?? 'To be confirmed'));

        // Portal account information
        if ($this->portalAccountCreated && $this->portalUsername) {
            $message->line('')
                    ->line('**Parent Portal Access**')
                    ->line('You can now log in to our parent portal to view attendance, results, fees, and announcements.')
                    ->line("**Username:** {$this->portalUsername}");

            if ($this->portalPassword) {
                $message->line("**Temporary Password:** {$this->portalPassword}")
                        ->line('Please change your password on first login for security.');
            }

            $message->action('Access Parent Portal', route('parent.login'));
        }

        $message->line('')
                ->line('Next Steps:')
                ->line('• Complete any remaining fee payments')
                ->line('• Attend the orientation program')
                ->line('• Collect school uniform and ID card')
                ->line('• Keep your contact details updated in the portal');

        $message->action('View Student Profile', route('admin.students.show', $this->student))
                ->line('We look forward to a successful and rewarding academic year with your child!')
                ->salutation('Warm regards,')
                ->salutation($this->school->name . ' Admissions & Student Affairs Team');

        return $message;
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'                  => 'student_enrolled',
            'student_id'            => $this->student->id,
            'student_name'          => $this->profile->full_name,
            'admission_number'      => $this->student->admission_number,
            'school_id'             => $this->school->id,
            'enrolled_at'           => now()->toIso8601String(),
            'portal_account_created'=> $this->portalAccountCreated,
            'action_url'            => route('admin.students.show', $this->student),
        ];
    }
}