<?php

namespace App\Notifications;

use App\Models\Housing\Hostel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for actions performed on a hostel.
 *
 * Notifies staff (e.g., admins, wardens) about hostel creation, updates, deletions, or restores.
 */
class HostelAction extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The hostel instance.
     *
     * @var Hostel
     */
    protected $hostel;

    /**
     * The action performed (created, updated, deleted, restored).
     *
     * @var string
     */
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param Hostel $hostel The hostel instance.
     * @param string $action The action performed.
     */
    public function __construct(Hostel $hostel, string $action)
    {
        $this->hostel = $hostel;
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $actionVerbs = [
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'restored' => 'restored',
        ];

        $verb = $actionVerbs[$this->action] ?? 'modified';

        return (new MailMessage)
            ->subject("Hostel {$this->action} Notification")
            ->line("The hostel '{$this->hostel->name}' has been {$verb}.")
            ->action('View Hostel', url('/hostels/' . $this->hostel->id))
            ->line('Thank you for using our school management system!');
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
            'hostel_id' => $this->hostel->id,
            'hostel_name' => $this->hostel->name,
            'action' => $this->action,
            'message' => "Hostel '{$this->hostel->name}' has been {$this->action}.",
        ];
    }
}
