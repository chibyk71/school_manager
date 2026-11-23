<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasSmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an administrator triggers a password reset.
 *
 * Security: Must never contain or expose plaintext passwords.
 * The recipient must set their own password via the forced-password-change flow.
 */
class AdminResetPasswordNotification extends Notification
{
    use Queueable, HasSmsChannel;

    /**
     * Create a new notification instance.
     */
    public function __construct() {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Password Has Been Reset')
            ->greeting('Hello ' . ($notifiable->full_name ?? $notifiable->username) . ',')
            ->line('Your password has been reset by an administrator.')
            ->line('Please use the password reset option on the login page to set a new password.')
            ->action('Log In to Your Account', url('/login'))
            ->line('If you did not request this, please contact your administrator.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * Must not include any password or credential material.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Your password has been reset by an administrator. Please set a new password on login.',
        ];
    }
}
