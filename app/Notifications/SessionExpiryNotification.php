<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// TODO 1: Import the necessary classes for using the Queueable trait and the MailMessage class.
// TODO 2: Use the Queueable trait in the SessionExpiryNotification class.
// TODO 3: Define a constructor method that accepts a session object as a parameter and assigns it to a property.
// TODO 4: Implement the via method to specify the delivery channels for the notification.
// TODO 5: Implement the toMail method to customize the email content for the notification.
// TODO 6: Implement the toArray method to customize the data stored in the database for the notification.
class SessionExpiryNotification extends Notification
{
    use Queueable;

    protected $session;
    // Add this property to store the session data

    /**
     * Create a new notification instance.
     */
    public function __construct($session)
    {
        $this->session = $session;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['mail', 'database']; // Sends email and in-app notifications
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Session Expiry Notification')
            ->line("The session '{$this->session->name}' is about to end on {$this->session->end}.")
            ->action('Manage Sessions', url('/sessions'))
            ->line('Please add a new session to avoid disruptions.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return [
            'message' => "The session '{$this->session->name}' is about to end on {$this->session->end}. Please add a new session.",
            'action_url' => url('/sessions'),
        ];
    }
}
