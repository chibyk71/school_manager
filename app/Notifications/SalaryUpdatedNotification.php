<?php

namespace App\Notifications;

use App\Models\Employee\Salary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for salary creation or updates.
 */
class SalaryUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The salary instance.
     *
     * @var Salary
     */
    protected $salary;

    /**
     * Create a new notification instance.
     *
     * @param Salary $salary
     */
    public function __construct(Salary $salary)
    {
        $this->salary = $salary;
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
        $subject = "Salary Updated for {$this->salary->departmentRole->name}";
        return (new MailMessage)
            ->subject($subject)
            ->line("The salary structure for the role {$this->salary->departmentRole->name} has been updated.")
            ->line("Base Salary: {$this->salary->base_salary}")
            ->line("Effective Date: {$this->salary->effective_date->format('Y-m-d')}")
            ->action('View Salary', route('salaries.show', $this->salary->id));
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
            'type' => 'salary_updated',
            'message' => "Salary for {$this->salary->departmentRole->name} has been updated.",
            'data' => [
                'salary_id' => $this->salary->id,
                'department_role_id' => $this->salary->department_role_id,
                'base_salary' => $this->salary->base_salary,
                'effective_date' => $this->salary->effective_date->format('Y-m-d'),
            ],
        ]);
    }
}