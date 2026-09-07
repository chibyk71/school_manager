<?php

namespace App\Notifications\Student;

use App\Models\Student\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentRequirementsOutstandingNotification extends Notification implements ShouldQueue
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
        $names = $this->extra['requirement_names'] ?? [];

        $mail = (new MailMessage)
            ->subject('Enrollment requirements outstanding')
            ->line('One or more enrollment requirements still need attention.');

        if (! empty($names) && is_array($names)) {
            foreach (array_slice($names, 0, 8) as $name) {
                $mail->line('• '.(string) $name);
            }
        }

        $mail->line('Please submit the required items so enrollment can proceed.');

        return $mail;
    }
}
