<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPasswordChanged extends Notification
{
    use Queueable;

    public $user;
    public $plainPassword;
    public $admin;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, string $plainPassword, User $admin)
    {
        $this->user = $user;
        $this->plainPassword = $plainPassword;
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
            ->subject('Your password has been reset')
            ->view('emails.password-changed')
            ->with([
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'admin' => $this->admin,
                'expires' => now()->addDay(),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'admin_id' => $this->admin->id,
            'plain_password' => $this->plainPassword,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'admin_id' => $this->admin->id,
            'plain_password' => $this->plainPassword,
        ];
    }
}
