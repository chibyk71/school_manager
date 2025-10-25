<?php

namespace App\Notifications;

use App\Models\Housing\HostelAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for actions performed on a hostel assignment.
 *
 * Notifies staff (e.g., admins, wardens) and the assigned student about assignment creation, updates, deletions, or restores.
 */
class HostelAssignmentAction extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The hostel assignment instance.
     *
     * @var HostelAssignment
     */
    protected $hostelAssignment;

    /**
     * The action performed (created, updated, deleted, restored).
     *
     * @var string
     */
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param HostelAssignment $hostelAssignment The hostel assignment instance.
     * @param string $action The action performed.
     */
    public function __construct(HostelAssignment $hostelAssignment, string $action)
    {
        $this->hostelAssignment = $hostelAssignment;
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
            ->subject("Hostel Assignment {$this->action} Notification")
            ->line("The hostel assignment for student '{$this->hostelAssignment->student->first_name} {$this->hostelAssignment->student->last_name}' in room '{$this->hostelAssignment->room->room_number}' (hostel '{$this->hostelAssignment->room->hostel->name}') has been {$verb}.")
            ->action('View Hostel Assignment', url('/hostel-assignments/' . $this->hostelAssignment->id))
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
            'hostel_assignment_id' => $this->hostelAssignment->id,
            'student_name' => "{$this->hostelAssignment->student->first_name} {$this->hostelAssignment->student->last_name}",
            'room_number' => $this->hostelAssignment->room->room_number,
            'hostel_name' => $this->hostelAssignment->room->hostel->name,
            'action' => $this->action,
            'message' => "Hostel assignment for student '{$this->hostelAssignment->student->first_name} {$this->hostelAssignment->student->last_name}' in room '{$this->hostelAssignment->room->room_number}' (hostel '{$this->hostelAssignment->room->hostel->name}') has been {$this->action}.",
        ];
    }
}
