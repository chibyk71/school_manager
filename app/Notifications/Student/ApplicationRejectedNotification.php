<?php

namespace App\Notifications\Student;

use App\Models\Student\StudentApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public StudentApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Application decision: '.$this->application->application_number)
            ->line('The application for '.$this->application->full_name.' was not approved.')
            ->line('Reference: '.$this->application->application_number);

        if ($this->application->rejection_reason) {
            $mail->line('Reason: '.$this->application->rejection_reason);
        }

        return $mail;
    }
}
