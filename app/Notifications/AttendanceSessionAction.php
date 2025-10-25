<?php

namespace App\Notifications;

use App\Models\Misc\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for attendance session actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class AttendanceSessionAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $attendanceSession;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param AttendanceSession $attendanceSession The attendance session.
     * @param string $action The action performed (create, update, delete, restore).
     */
    public function __construct(AttendanceSession $attendanceSession, string $action = 'created')
    {
        $this->attendanceSession = $attendanceSession;
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
        $subject = "Attendance Session {$action}";
        $name = $this->attendanceSession->name;
        $date = $this->attendanceSession->date_effective->format('Y-m-d');
        $classSection = $this->attendanceSession->classSection->name ?? 'Unknown';
        $manager = $this->attendanceSession->manager->name ?? 'Unknown';

        return (new MailMessage)
            ->subject($subject)
            ->line("An attendance session has been {$this->action}.")
            ->line("Name: {$name}")
            ->line("Date: {$date}")
            ->line("Class Section: {$classSection}")
            ->line("Manager: {$manager}")
            ->action('View Attendance Session', route('attendance-sessions.show', $this->attendanceSession->id))
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
            'attendance_session_id' => $this->attendanceSession->id,
            'action' => $this->action,
            'message' => "Attendance session {$this->action}: {$this->attendanceSession->name} on {$this->attendanceSession->date_effective->format('Y-m-d')}.",
        ];
    }
}
