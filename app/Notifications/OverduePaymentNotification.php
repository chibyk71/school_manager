<?php

namespace App\Notifications;

use App\Models\Finance\FeeInstallmentDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverduePaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $feeInstallmentDetail;

    public function __construct(FeeInstallmentDetail $feeInstallmentDetail)
    {
        $this->feeInstallmentDetail = $feeInstallmentDetail;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'message' => "Overdue payment: Amount {$this->feeInstallmentDetail->amount} for Student ID {$this->feeInstallmentDetail->user_id} (Due: {$this->feeInstallmentDetail->due_date->format('Y-m-d')})",
            'fee_installment_detail_id' => $this->feeInstallmentDetail->id,
            'school_id' => $this->feeInstallmentDetail->school_id,
            'action' => 'overdue',
        ]);
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Overdue Payment Notification')
            ->line('An installment payment is overdue.')
            ->line("Amount: {$this->feeInstallmentDetail->amount}")
            ->line("Due Date: {$this->feeInstallmentDetail->due_date->format('Y-m-d')}")
            ->line("Student ID: {$this->feeInstallmentDetail->user_id}")
            ->action('View Installment Detail', route('fee-installment-details.show', $this->feeInstallmentDetail->id));
    }
}