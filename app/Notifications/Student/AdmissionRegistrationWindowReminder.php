<?php

namespace App\Notifications\Student;

use App\Models\Student\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dedicated Phase 7 notification: registration window ending for an accepted offer.
 */
class AdmissionRegistrationWindowReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Admission $admission,
        public array $extra = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Reminder: registration window closing')
            ->line('Your admission has been accepted. The registration window is closing soon.');

        if ($this->admission->registration_ends_at) {
            $mail->line('Registration ends: '.$this->admission->registration_ends_at->toDayDateTimeString());
        }
        if ($this->admission->registration_starts_at) {
            $mail->line('Registration opens: '.$this->admission->registration_starts_at->toDayDateTimeString());
        }
        if ($this->admission->admission_number) {
            $mail->line('Admission number: '.$this->admission->admission_number);
        }

        $mail->line('Please complete registration before the window closes.');

        return $mail;
    }
}
