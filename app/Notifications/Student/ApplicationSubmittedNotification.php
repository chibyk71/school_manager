<?php

namespace App\Notifications\Student;

use App\Models\Student\StudentApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification implements ShouldQueue
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
            ->subject('Application received: '.$this->application->application_number)
            ->line('We received an application for '.$this->application->full_name.'.')
            ->line('Reference: '.$this->application->application_number)
            ->line('This confirms receipt only. It does not mean the candidate is admitted or enrolled.');
    }
}
