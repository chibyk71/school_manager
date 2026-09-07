<?php

namespace App\Notifications\Student;

use App\Models\Student\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentIncompleteNotification extends Notification implements ShouldQueue
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
        $isReminder = (bool) ($this->extra['reminder'] ?? false);
        $blockers = $this->extra['blockers'] ?? [];

        $mail = (new MailMessage)
            ->subject($isReminder
                ? 'Reminder: enrollment incomplete'
                : 'Enrollment incomplete – action required')
            ->line($isReminder
                ? 'This is a reminder that enrollment is still incomplete.'
                : 'Enrollment requires additional information or documents before it can be finalized.');

        if (! empty($blockers) && is_array($blockers)) {
            foreach (array_slice($blockers, 0, 5) as $blocker) {
                $mail->line('• '.(string) $blocker);
            }
        }

        $mail->line('Please complete outstanding items or contact the school office.');

        return $mail;
    }
}
