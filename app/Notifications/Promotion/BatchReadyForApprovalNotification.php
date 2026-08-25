<?php

namespace App\Notifications\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BatchReadyForApprovalNotification
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Sent when a new PromotionBatch has been created and populated (status = 'pending').
 *
 * Purpose:
 * - Notify users with 'promotions.approve' permission that a batch is ready for review/approval.
 * - Includes direct link to the batch review page.
 * - Supports Database + Mail channels (SMS can be added later).
 *
 * Used by: NotifyOnBatchReadyForApproval listener
 */

class BatchReadyForApprovalNotification extends Notification
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
            'type' => 'promotion_batch_ready',
            'batch_id' => $this->batch->id,
            'batch_name' => $this->batch->name,
            'session_name' => $this->batch->academicSession?->name,
            'total_students' => $this->batch->total_students,
            'message' => "Promotion batch '{$this->batch->name}' is ready for review and approval.",
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('promotion.batches.show', $this->batch);

        return (new MailMessage)
            ->subject("Promotion Batch Ready for Approval: {$this->batch->name}")
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("A new promotion batch **{$this->batch->name}** for {$this->batch->academicSession?->name} has been generated and is now ready for review.")
            ->line("Total students: **{$this->batch->total_students}**")
            ->action('Review & Approve Batch', $url)
            ->line('Please review the student recommendations and approve the batch when ready.');
    }
}