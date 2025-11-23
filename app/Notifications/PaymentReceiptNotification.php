<?php

namespace App\Notifications;

use App\Models\Finance\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected Payment $payment) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment Received – ₦" . number_format($this->payment->payment_amount, 2))
            ->greeting("Hello {$notifiable->name},")
            ->line("We have received your payment of **₦" . number_format($this->payment->payment_amount, 2) . "**.")
            ->line("**Reference:** {$this->payment->payment_reference}")
            ->line("**Date:** " . $this->payment->payment_date->format('d M Y'))
            ->line("Thank you for your prompt payment!")
            ->action('View Receipt', url('/student/payments'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'amount' => $this->payment->payment_amount,
            'reference' => $this->payment->payment_reference,
            'date' => $this->payment->payment_date,
            'message' => "Payment of ₦" . number_format($this->payment->payment_amount, 2) . " received",
        ];
    }
}
