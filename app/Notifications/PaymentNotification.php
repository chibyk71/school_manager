<?php

namespace App\Notifications;

use App\Models\Finance\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param Payment $payment
     * @param string $action
     */
    public function __construct(Payment $payment, string $action)
    {
        $this->payment = $payment;
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
        return ['database', 'mail'];
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
            'message' => "A payment has been {$this->action}: Amount {$this->payment->payment_amount} ({$this->payment->payment_currency}) for Student ID {$this->payment->user_id} (Status: {$this->payment->payment_status})",
            'payment_id' => $this->payment->id,
            'school_id' => $this->payment->school_id,
            'action' => $this->action,
        ]);
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Payment {$this->action} Notification")
            ->line("A payment has been {$this->action}.")
            ->line("Amount: {$this->payment->payment_amount} {$this->payment->payment_currency}")
            ->line("Status: {$this->payment->payment_status}")
            ->line("Reference: {$this->payment->payment_reference}")
            ->line("Date: {$this->payment->payment_date->format('Y-m-d H:i:s')}")
            ->line("Description: {$this->payment->payment_description}")
            ->action('View Payment', route('payments.show', $this->payment->id));
    }
}