<?php

namespace App\Notifications\Student;

use App\Models\Student\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dedicated Phase 7 notification: admission offer acceptance deadline approaching.
 * Distinct from AdmissionOfferedNotification (offer issued).
 */
class AdmissionAcceptanceDeadlineReminder extends Notification implements ShouldQueue
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
            ->subject('Reminder: admission offer acceptance deadline approaching')
            ->line('This is a reminder that your admission offer deadline is approaching.');

        if ($this->admission->acceptance_deadline) {
            $mail->line('Acceptance deadline: '.$this->admission->acceptance_deadline->toDayDateTimeString());
        }

        if ($this->admission->admission_number) {
            $mail->line('Admission number: '.$this->admission->admission_number);
        }

        $mail->line('Please respond before the deadline. Contact the school if you need assistance.');

        return $mail;
    }
}
