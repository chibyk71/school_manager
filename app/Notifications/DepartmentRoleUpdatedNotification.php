<?php

namespace App\Notifications;

use App\Models\Employee\DepartmentRole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for department role creation or updates.
 */
class DepartmentRoleUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The department role instance.
     *
     * @var DepartmentRole
     */
    protected $departmentRole;

    /**
     * Create a new notification instance.
     *
     * @param DepartmentRole $departmentRole
     */
    public function __construct(DepartmentRole $departmentRole)
    {
        $this->departmentRole = $departmentRole;
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
        $subject = "Department Role Updated - {$this->departmentRole->name}";
        return (new MailMessage)
            ->subject($subject)
            ->line("The department role '{$this->departmentRole->name}' has been updated.")
            ->line("Department: {$this->departmentRole->department->name}")
            ->line("Role: {$this->departmentRole->role->name}")
            ->line("Section: " . (isset($this->departmentRole->schoolSection) ? $this->departmentRole->schoolSection->name : 'None') . '"')
            ->action('View Department Role', route('department-roles.show', $this->departmentRole->id));
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
            'type' => 'department_role_updated',
            'message' => "Department role '{$this->departmentRole->name}' has been updated.",
            'data' => [
                'department_role_id' => $this->departmentRole->id,
                'school_id' => $this->departmentRole->school_id,
                'department_id' => $this->departmentRole->department_id,
                'role_id' => $this->departmentRole->role_id,
                'school_section_id' => $this->departmentRole->school_section_id,
                'name' => $this->departmentRole->name,
            ],
        ]);
    }
}