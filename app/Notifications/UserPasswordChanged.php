<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a user's password has been changed.
 *
 * Security: Must never contain or expose plaintext passwords.
 * The recipient already knows their new password (they set it themselves).
 * Database notifications must not store credential material.
 */
class UserPasswordChanged extends Notification
{
    use Queueable;

    public User $user;
    public User $admin;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user  The account whose password changed.
     * @param  User  $admin  The administrator who performed the change (or the user).
     */
    public function __construct(User $user, User $admin)
    {
        $this->user = $user;
        $this->admin = $admin;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password has been changed')
            ->greeting('Hello ' . ($this->user->full_name ?? $this->user->username) . ',')
            ->line('Your password has been changed successfully.')
            ->line('If you did not make this change, please contact your administrator immediately.')
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
            'user_id' => $this->user->id,
            'admin_id' => $this->admin->id,
            'message' => 'Password changed successfully.',
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * Must not include any password or credential material.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
