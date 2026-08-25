<?php

namespace App\Notifications\Student;

use App\Events\Student\ApplicationRejected as ApplicationRejectedEvent;
use App\Models\Student\StudentApplication;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ApplicationRejected Notification (v2.0 – Production-Ready)
 *
 * Sent to the guardians (and optionally the applicant) when a student application 
 * has been reviewed and rejected by the admissions team.
 *
 * This notification includes the rejection reason and any next steps or encouragement 
 * to re-apply in the future, maintaining a respectful and constructive tone.
 *
 * Features / Problems Solved:
 * - Delivers clear, empathetic communication with the exact rejection reason.
 * - Provides guidance on what to do next (re-apply, contact admissions, etc.).
 * - Maintains professionalism and school brand voice.
 * - Queuable for performance.
 * - Stores useful data in the database notification for later reference.
 *
 * Fits into the Student Management Module:
 * - Triggered by a listener listening to the ApplicationRejected event.
 * - Sent primarily to the guardians listed in the application.
 */

class ApplicationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public StudentApplication $application;
    public School $school;
    public string $rejectionReason;

    /**
     * Create a new notification instance.
     */
    public function __construct(ApplicationRejectedEvent $event)
    {
        $this->application = $event->application;
        $this->school = $event->school;
        $this->rejectionReason = $event->rejectionReason;
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
            ->subject("Update on Your Application - {$this->application->full_name}")
            ->greeting('Dear ' . ($notifiable->profile?->full_name ?? $notifiable->name) . ',')
            ->line("Thank you for submitting an application for **{$this->application->full_name}** to {$this->school->name}.")
            ->line("After careful review, we regret to inform you that the application has been **rejected**.")
            ->line("**Rejection Reason:**")
            ->line($this->rejectionReason)
            ->line('')
            ->line('We encourage you to address the above points and re-apply in the next admission cycle, or contact our admissions office for clarification.')
            ->action('View Application Details', route('admin.applications.show', $this->application))
            ->line('We appreciate your interest in our school and wish you all the best.')
            ->salutation('Best regards,')
            ->salutation($this->school->name . ' Admissions Team');
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_rejected',
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'applicant_name' => $this->application->full_name,
            'rejection_reason' => $this->rejectionReason,
            'school_id' => $this->school->id,
            'rejected_at' => now()->toIso8601String(),
            'action_url' => route('admin.applications.show', $this->application),
        ];
    }
}