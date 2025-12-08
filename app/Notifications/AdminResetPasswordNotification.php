<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasSmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminResetPasswordNotification extends Notification
{
    use Queueable, HasSmsChannel;

    /**
     * The temporary password.
     *
     * @var string
     */
    public string $tempPassword;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $tempPassword)
    {
        $this->tempPassword = $tempPassword;
    }

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
            ->greeting('Hello ' . $notifiable->full_name . ',')
            ->line('Your password has been reset by an administrator.')
            ->line('Your temporary password is: **' . $this->tempPassword . '**')
            ->line('Please log in and change your password immediately.')
            ->action('Log In to Your Account', url('/login'))
            ->line('If you did not request this, please contact your administrator.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'temp_password' => $this->tempPassword,
            'message' => 'Password reset by admin.',
        ];
    }
}
