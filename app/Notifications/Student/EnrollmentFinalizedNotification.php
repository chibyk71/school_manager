<?php

namespace App\Notifications\Student;

use App\Models\Student\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentFinalizedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Enrollment $enrollment,
        public array $extra = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reg = data_get($this->enrollment->meta, 'registration_number');
        $admNum = data_get($this->enrollment->meta, 'admission_number');

        $mail = (new MailMessage)
            ->subject('Enrollment finalized')
            ->line('Enrollment has been finalized successfully.');

        if ($admNum) {
            $mail->line('Admission number: '.$admNum);
        }
        if ($reg) {
            $mail->line('Registration number: '.$reg);
        }

        $mail->line('Please contact the school if you have questions about next steps.');

        return $mail;
    }
}
