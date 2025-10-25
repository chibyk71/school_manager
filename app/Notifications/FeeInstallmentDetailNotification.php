<?php

namespace App\Notifications;

use App\Models\Finance\FeeInstallmentDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeInstallmentDetailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $feeInstallmentDetail;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @param string $action
     */
    public function __construct(FeeInstallmentDetail $feeInstallmentDetail, string $action)
    {
        $this->feeInstallmentDetail = $feeInstallmentDetail;
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
            'message' => "A fee installment detail has been {$this->action}: Amount {$this->feeInstallmentDetail->amount} for Student ID {$this->feeInstallmentDetail->user_id} (Due: {$this->feeInstallmentDetail->due_date->format('Y-m-d')}, Status: {$this->feeInstallmentDetail->status})",
            'fee_installment_detail_id' => $this->feeInstallmentDetail->id,
            'school_id' => $this->feeInstallmentDetail->school_id,
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
            ->subject("Fee Installment Detail {$this->action} Notification")
            ->line("A fee installment detail has been {$this->action}.")
            ->line("Amount: {$this->feeInstallmentDetail->amount}")
            ->line("Due Date: {$this->feeInstallmentDetail->due_date->format('Y-m-d')}")
            ->line("Status: {$this->feeInstallmentDetail->status}")
            ->line(sprintf(
                'Punishment: %s',
                $this->feeInstallmentDetail->punishment ?? 'N/A'
            ))
            ->line(sprintf(
                'Paid Date: %s',
                $this->feeInstallmentDetail->paid_date ? $this->feeInstallmentDetail->paid_date->format('Y-m-d') : 'N/A'
            ))
            ->action('View Fee Installment Detail', route('fee-installment-details.show', $this->feeInstallmentDetail->id));
    }
}