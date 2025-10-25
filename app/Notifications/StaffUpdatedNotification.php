<?php

namespace App\Notifications;

use App\Models\Employee\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for staff creation or updates.
 */
class StaffUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The staff instance.
     *
     * @var Staff
     */
    protected $staff;

    /**
     * Create a new notification instance.
     *
     * @param Staff $staff
     */
    public function __construct(Staff $staff)
    {
        $this->staff = $staff;
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
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $subject = "Staff Record Updated - {$this->staff->user->name}";
        return (new MailMessage)
            ->subject($subject)
            ->line("Your staff record has been updated.")
            ->line(sprintf('Role: %s', $this->staff->departmentRole->name ?? 'None'))
            ->line("School: {$this->staff->school->name}")
            ->action('View Staff Record', route('staff.show', $this->staff->id));
    }

    /**
     * Get the database representation of the notification.
     *
     * @param mixed $notifiable
     * @return DatabaseMessage
     */
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'type' => 'staff_updated',
            'message' => "Staff record for {$this->staff->user->name} has been updated.",
            'data' => [
                'staff_id' => $this->staff->id,
                'user_id' => $this->staff->user_id,
                'school_id' => $this->staff->school_id,
                'department_role_id' => $this->staff->department_role_id,
            ],
        ]);
    }
}