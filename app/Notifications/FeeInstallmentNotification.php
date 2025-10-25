<?php

namespace App\Notifications;

use App\Models\Finance\FeeInstallment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeInstallmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $feeInstallment;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param FeeInstallment $feeInstallment
     * @param string $action
     */
    public function __construct(FeeInstallment $feeInstallment, string $action)
    {
        $this->feeInstallment = $feeInstallment;
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
            'message' => "A fee installment has been {$this->action}: Installment {$this->feeInstallment->no_of_installment} for Fee ID {$this->feeInstallment->fee_id} (Due: {$this->feeInstallment->due_date->format('Y-m-d')})",
            'fee_installment_id' => $this->feeInstallment->id,
            'school_id' => $this->feeInstallment->school_id,
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
            ->subject("Fee Installment {$this->action} Notification")
            ->line("A fee installment has been {$this->action}.")
            ->line("Installment Number: {$this->feeInstallment->no_of_installment}")
            ->line("Fee ID: {$this->feeInstallment->fee_id}")
            ->line("Initial Amount Payable: {$this->feeInstallment->initial_amount_payable}")
            ->line("Due Date: {$this->feeInstallment->due_date->format('Y-m-d')}")
            ->action('View Fee Installment', route('fee-installments.show', $this->feeInstallment->id));
    }
}