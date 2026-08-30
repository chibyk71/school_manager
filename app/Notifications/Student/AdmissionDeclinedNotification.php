<?php

namespace App\Notifications\Student;

use App\Models\Student\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdmissionDeclinedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('Admission offer declined')
            ->line('The admission offer has been declined.');
    }
}
