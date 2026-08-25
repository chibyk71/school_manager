<?php

namespace App\Notifications\Student;

use App\Events\Student\ApplicationSubmitted;
use App\Models\Student\StudentApplication;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * NewApplicationSubmitted Notification (v2.0 – Production-Ready)
 *
 * Sent to school administrators when a new student application is submitted,
 * whether through the public portal or created directly by an admin.
 *
 * This notification serves as the primary alert for the admissions team.
 *
 * Features / Problems Solved:
 * - Clear, actionable notification for admins.
 * - Includes direct link to review the application.
 * - Supports both public and admin submissions with appropriate wording.
 * - Queuable for better performance under load.
 * - Uses Laravel's notification system (mail, database, broadcast possible).
 *
 * Fits into the Student Management Module:
 * - Triggered by Listener listening to ApplicationSubmitted event.
 * - Sent to users with permission `applications.view` or role `admin` / `admissions`.
 */

class NewApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public StudentApplication $application;
    public School $school;
    public bool $isPublicSubmission;

    /**
     * Create a new notification instance.
     */
    public function __construct(ApplicationSubmitted $event)
    {
        $this->application = $event->application;
        $this->school = $event->school;
        $this->isPublicSubmission = $event->isPublicSubmission;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database']; // Can add 'broadcast' later if needed
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->isPublicSubmission
            ? "New Public Application Received - {$this->application->full_name}"
            : "New Application Created by Admin - {$this->application->full_name}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new student application has been submitted.')
            ->line("**Applicant:** {$this->application->full_name}")
            ->line("**Application Number:** {$this->application->application_number}")
            ->line("**Submitted Via:** " . ($this->isPublicSubmission ? 'Public Portal' : 'Admin'))
            ->line("**Desired Session:** " . ($this->application->academicSession?->name ?? 'Not specified'))
            ->action('Review Application', route('admin.applications.show', $this->application))
            ->line('Please review this application at your earliest convenience.');
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_application',
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'applicant_name' => $this->application->full_name,
            'source' => $this->application->source,
            'school_id' => $this->school->id,
            'submitted_at' => $this->application->submitted_at?->toIso8601String(),
            'is_public' => $this->isPublicSubmission,
            'action_url' => route('admin.applications.show', $this->application),
        ];
    }
}
