<?php

namespace App\Notifications\Student;

use App\Models\Student\StudentApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public StudentApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Application approved: '.$this->application->application_number)
            ->line('The application for '.$this->application->full_name.' has passed review.')
            ->line('Reference: '.$this->application->application_number)
            ->line('This does not mean the candidate is enrolled. Further admission steps may still apply.');
    }
}
