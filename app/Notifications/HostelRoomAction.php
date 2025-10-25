<?php

namespace App\Notifications;

use App\Models\Housing\HostelRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for actions performed on a hostel room.
 *
 * Notifies staff (e.g., admins, wardens) about hostel room creation, updates, deletions, or restores.
 */
class HostelRoomAction extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The hostel room instance.
     *
     * @var HostelRoom
     */
    protected $hostelRoom;

    /**
     * The action performed (created, updated, deleted, restored).
     *
     * @var string
     */
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param HostelRoom $hostelRoom The hostel room instance.
     * @param string $action The action performed.
     */
    public function __construct(HostelRoom $hostelRoom, string $action)
    {
        $this->hostelRoom = $hostelRoom;
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
            ->subject("Hostel Room {$this->action} Notification")
            ->line("The hostel room '{$this->hostelRoom->room_number}' in hostel '{$this->hostelRoom->hostel->name}' has been {$verb}.")
            ->action('View Hostel Room', url('/hostel-rooms/' . $this->hostelRoom->id))
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
            'hostel_room_id' => $this->hostelRoom->id,
            'hostel_room_number' => $this->hostelRoom->room_number,
            'hostel_name' => $this->hostelRoom->hostel->name,
            'action' => $this->action,
            'message' => "Hostel room '{$this->hostelRoom->room_number}' in hostel '{$this->hostelRoom->hostel->name}' has been {$this->action}.",
        ];
    }
}
