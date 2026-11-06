<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * OTP password-recovery notification delivered on-demand to the Person primary email.
 * Must not trust a caller-supplied SMS destination.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(
        public string $token,
        public $user,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $email = is_object($notifiable) && isset($notifiable->email)
            ? $notifiable->email
            : (is_object($notifiable) && isset($notifiable->routes['mail']) ? $notifiable->routes['mail'] : null);

        $url = route('password.reset', array_filter([
            'token' => $this->token,
            'email' => $email,
        ]));

        $username = $this->user->username ?? $this->user->name ?? 'user';

        return (new MailMessage)
            ->subject('Password Reset Request')
            ->line('You are receiving this email because we received a password reset request.')
            ->line('Username: '.$username)
            ->line('OTP: '.$this->token)
            ->action('Reset Password', $url)
            ->line('If you did not request a password reset, no further action is required.');
    }
}
