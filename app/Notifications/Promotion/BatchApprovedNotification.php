<?php

namespace App\Notifications\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BatchApprovedNotification
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Sent when a PromotionBatch has been approved (status changed to 'approved').
 *
 * Purpose:
 * - Notify users with 'promotions.execute' permission that the batch is ready for execution.
 * - Notify the original batch initiator.
 * - Provides clear next step (execution).
 *
 * Used by: NotifyOnBatchApproved listener
 */

class BatchApprovedNotification extends Notification
{
    use Queueable;

    public PromotionBatch $batch;

    /**
     * Create a new notification instance.
     */
    public function __construct(PromotionBatch $batch)
    {
        $this->batch = $batch;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the array representation for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'promotion_batch_approved',
            'batch_id' => $this->batch->id,
            'batch_name' => $this->batch->name,
            'approved_by' => $this->batch->approvedBy?->name,
            'approved_at' => $this->batch->approved_at?->toDateTimeString(),
            'message' => "Promotion batch '{$this->batch->name}' has been approved and is ready for execution.",
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('promotion.batches.show', $this->batch);

        return (new MailMessage)
            ->subject("Promotion Batch Approved: {$this->batch->name}")
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("The promotion batch **{$this->batch->name}** has been approved.")
            ->line("Approved by: " . ($this->batch->approvedBy?->name ?? 'System'))
            ->line("Total students: **{$this->batch->total_students}**")
            ->action('View Batch & Execute', $url)
            ->line('You can now proceed with executing the promotion.');
    }
}