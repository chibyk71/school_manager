<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Narrow credential-delivery notification for newly provisioned accounts.
 * Not a general domain event — plaintext is only used to compose this mail.
 */
class AccountCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $user,
        public ?string $temporaryPassword = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your school manager account')
            ->line('An authentication account was created for you.')
            ->line('Username: '.$this->user->username);

        if (filled($this->temporaryPassword)) {
            $mail->line('Temporary password: '.$this->temporaryPassword)
                ->line('You will be required to change this password after signing in.');
        }

        return $mail->action('Sign in', url('/login'));
    }
}
