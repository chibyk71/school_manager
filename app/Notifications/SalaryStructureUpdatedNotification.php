<?php

namespace App\Notifications;

use App\Models\Employee\SalaryStructure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for salary structure creation or updates.
 */
class SalaryStructureUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The salary structure instance.
     *
     * @var SalaryStructure
     */
    protected $salaryStructure;

    /**
     * Create a new notification instance.
     *
     * @param SalaryStructure $salaryStructure
     */
    public function __construct(SalaryStructure $salaryStructure)
    {
        $this->salaryStructure = $salaryStructure;
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
        $subject = "Salary Structure Updated - {$this->salaryStructure->name}";
        return (new MailMessage)
            ->subject($subject)
            ->line("The salary structure '{$this->salaryStructure->name}' for the role {$this->salaryStructure->departmentRole->name} has been updated.")
            ->line("Amount: {$this->salaryStructure->amount} {$this->salaryStructure->currency}")
            ->line("Effective Date: {$this->salaryStructure->effective_date->format('Y-m-d')}")
            ->action('View Salary Structure', route('salary-structures.show', $this->salaryStructure->id));
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
            'type' => 'salary_structure_updated',
            'message' => "Salary structure '{$this->salaryStructure->name}' for {$this->salaryStructure->departmentRole->name} has been updated.",
            'data' => [
                'salary_structure_id' => $this->salaryStructure->id,
                'salary_id' => $this->salaryStructure->salary_id,
                'department_role_id' => $this->salaryStructure->department_role_id,
                'amount' => $this->salaryStructure->amount,
                'currency' => $this->salaryStructure->currency,
                'effective_date' => $this->salaryStructure->effective_date->format('Y-m-d'),
            ],
        ]);
    }
}