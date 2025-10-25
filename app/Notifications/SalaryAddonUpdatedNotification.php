<?php

namespace App\Notifications;

use App\Models\Employee\SalaryAddon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalaryAddonUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $salaryAddon;

    public function __construct(SalaryAddon $salaryAddon)
    {
        $this->salaryAddon = $salaryAddon;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $subject = "Salary Addon Updated - {$this->salaryAddon->name}";
        return (new MailMessage)
            ->subject($subject)
            ->line("A {$this->salaryAddon->type} addon '{$this->salaryAddon->name}' has been updated for you.")
            ->line("Amount: {$this->salaryAddon->amount}")
            ->line("Effective Date: {$this->salaryAddon->effective_date->format('Y-m-d')}")
            ->line(sprintf(
                'Recurrence: %s',
                $this->salaryAddon->recurrence ?? 'None'
            ))
            ->action('View Salary Addon', route('salary-addons.show', $this->salaryAddon->id));
    }

    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'type' => 'salary_addon_updated',
            'message' => "Salary addon '{$this->salaryAddon->name}' ({$this->salaryAddon->type}) has been updated.",
            'data' => [
                'salary_addon_id' => $this->salaryAddon->id,
                'staff_id' => $this->salaryAddon->staff_id,
                'name' => $this->salaryAddon->name,
                'type' => $this->salaryAddon->type,
                'amount' => $this->salaryAddon->amount,
                'effective_date' => $this->salaryAddon->effective_date->format('Y-m-d'),
                'recurrence' => $this->salaryAddon->recurrence,
                'recurrence_end_date' => $this->salaryAddon->recurrence_end_date?->format('Y-m-d'),
            ],
        ]);
    }
}