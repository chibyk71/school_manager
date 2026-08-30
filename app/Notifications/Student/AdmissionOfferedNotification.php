<?php

namespace App\Notifications\Student;

use App\Models\Student\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdmissionOfferedNotification extends Notification implements ShouldQueue
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
        $isReminder = (bool) ($this->extra['reminder'] ?? false);
        $subject = $isReminder
            ? 'Reminder: admission offer deadline approaching'
            : 'Admission offer issued';

        $mail = (new MailMessage)
            ->subject($subject)
            ->line($isReminder
                ? 'This is a reminder that your admission offer deadline is approaching.'
                : 'You have been offered a place.');

        if ($this->admission->acceptance_deadline) {
            $mail->line('Acceptance deadline: '.$this->admission->acceptance_deadline->toDayDateTimeString());
        }
        if ($this->admission->registration_date) {
            $mail->line('Expected registration date: '.$this->admission->registration_date->toDateString());
        }
        if ($this->admission->registration_starts_at || $this->admission->registration_ends_at) {
            $mail->line(sprintf(
                'Registration window: %s – %s',
                $this->admission->registration_starts_at?->toDayDateTimeString() ?? 'open',
                $this->admission->registration_ends_at?->toDayDateTimeString() ?? 'open'
            ));
        }

        $mail->line('Accepting this offer does not complete enrollment. Further registration steps may apply.');

        return $mail;
    }
}
