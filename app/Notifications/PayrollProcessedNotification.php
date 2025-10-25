<?php

namespace App\Notifications;

use App\Models\Employee\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for payroll processing (e.g., marked as paid).
 */
class PayrollProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The payroll instance.
     *
     * @var Payroll
     */
    protected $payroll;

    /**
     * Create a new notification instance.
     *
     * @param Payroll $payroll
     */
    public function __construct(Payroll $payroll)
    {
        $this->payroll = $payroll;
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
        $subject = "Payroll Processed - {$this->payroll->payment_date->format('Y-m-d')}";
        return (new MailMessage)
            ->subject($subject)
            ->line("Your payroll for {$this->payroll->payment_date->format('Y-m-d')} has been processed with a net salary of {$this->payroll->net_salary}.")
            ->line("Status: {$this->payroll->status}")
            ->line("Description: {$this->payroll->description}")
            ->action('View Payroll', route('payrolls.show', $this->payroll->id));
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
            'type' => 'payroll_processed',
            'message' => "Your payroll for {$this->payroll->payment_date->format('Y-m-d')} has been processed.",
            'data' => [
                'payroll_id' => $this->payroll->id,
                'net_salary' => $this->payroll->net_salary,
                'payment_date' => $this->payroll->payment_date->format('Y-m-d'),
                'status' => $this->payroll->status,
            ],
        ]);
    }
}