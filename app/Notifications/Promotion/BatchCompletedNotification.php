<?php

namespace App\Notifications\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BatchCompletedNotification
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Sent when a PromotionBatch has been fully executed and reaches 'completed' status.
 *
 * Purpose:
 * - Notify school administrators, initiators, and approvers that the entire promotion cycle is finished.
 * - Provide summary statistics (total processed, failed, etc.).
 * - Encourage generation of reports/transcripts/certificates.
 * - Supports database + mail channels (SMS enabled by default for this critical event).
 *
 * Used by: NotifyOnBatchCompleted listener
 */

class BatchCompletedNotification extends Notification
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
     * Get the array representation for database storage (in-app notifications).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'promotion_batch_completed',
            'batch_id' => $this->batch->id,
            'batch_name' => $this->batch->name,
            'session_name' => $this->batch->academicSession?->name,
            'total_students' => $this->batch->total_students,
            'processed_students' => $this->batch->processed_students,
            'failed_students' => $this->batch->failed_students,
            'message' => "Promotion batch '{$this->batch->name}' has been successfully completed.",
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('promotion.batches.show', $this->batch);

        return (new MailMessage)
            ->subject("Promotion Completed: {$this->batch->name}")
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("The promotion batch **{$this->batch->name}** has been successfully completed.")
            ->line("Session: {$this->batch->academicSession?->name}")
            ->line("Total students: **{$this->batch->total_students}**")
            ->line("Successfully processed: **{$this->batch->processed_students}**")
            ->line("Failed: **{$this->batch->failed_students}**")
            ->action('View Promotion Results', $url)
            ->line('You can now generate reports, transcripts, and certificates for this session.');
    }
}