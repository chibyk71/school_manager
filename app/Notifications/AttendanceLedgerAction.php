<?php

namespace App\Notifications;

use App\Models\Misc\AttendanceLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for attendance ledger actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class AttendanceLedgerAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $attendanceLedger;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param AttendanceLedger $attendanceLedger The attendance ledger.
     * @param string $action The action performed (create, update, delete, restore).
     */
    public function __construct(AttendanceLedger $attendanceLedger, string $action = 'created')
    {
        $this->attendanceLedger = $attendanceLedger;
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array<string>
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
        $action = ucfirst($this->action);
        $subject = "Attendance Ledger {$action}";
        $status = $this->attendanceLedger->status;
        $session = $this->attendanceLedger->attendanceSession->name ?? 'Unknown Session';
        $attendable = $this->attendanceLedger->attendable ? class_basename($this->attendanceLedger->attendable_type) . ': ' . ($this->attendanceLedger->attendable->name ?? 'Unknown') : 'Unknown';

        return (new MailMessage)
            ->subject($subject)
            ->line("An attendance ledger has been {$this->action}.")
            ->line("Attendable: {$attendable}")
            ->line("Session: {$session}")
            ->line("Status: {$status}")
            ->line(sprintf('Remarks: %s', $this->attendanceLedger->remarks ?? 'None'))
            ->action('View Attendance Ledger', route('attendance-ledgers.show', $this->attendanceLedger->id))
            ->line('Thank you for using our school management system!');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @param mixed $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return [
            'attendance_ledger_id' => $this->attendanceLedger->id,
            'action' => $this->action,
            'message' => sprintf(
                'Attendance ledger %s for %s in session %s.',
                $this->action,
                $this->attendanceLedger->status,
                $this->attendanceLedger->attendanceSession->name ?? 'Unknown'
            ),
        ];
    }
}
