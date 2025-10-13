<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MadeAdminOfSchoolNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $school;

    /**
     * Create a new notification instance.
     *
     * @param School $school
     */
    public function __construct(School $school)
    {
        $this->school = $school;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('You Have Been Assigned as an Admin')
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned as an administrator for {$this->school->name}.")
            ->line("School Type: " . ucfirst($this->school->tenancy_type))
            ->line("Email: {$this->school->email}")
            ->action('View School', url('/schools/' . $this->school->slug))
            ->line('Please log in to manage the school\'s settings, users, and academic sessions.')
            ->line('If you believe this is an error, contact support.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'school_id' => $this->school->id,
            'school_name' => $this->school->name,
            'tenancy_type' => $this->school->tenancy_type,
            'message' => "You have been assigned as an admin for {$this->school->name}.",
        ];
    }
}
