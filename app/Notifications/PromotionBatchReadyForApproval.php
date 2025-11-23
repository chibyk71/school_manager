<?php
// app/Notifications/PromotionBatchReadyForApproval.php

namespace App\Notifications;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PromotionBatchReadyForApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected PromotionBatch $batch)
    {
        //
    }

    public function via($notifiable): array
    {
        return ['mail', 'database']; // + 'sms' later via Termii
    }

    public function toMail($notifiable): MailMessage
    {
        $url = route('promotions.review', $this->batch);

        return (new MailMessage)
            ->subject("Promotion Ready: {$this->batch->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The promotion batch for **{$this->batch->academicSession->name}** is ready for your review.")
            ->line("Total students: **{$this->batch->total_students}**")
            ->action('Review & Approve', $url)
            ->line('Thank you for keeping our school running smoothly!');
    }

    public function toArray($notifiable): array
    {
        return [
            'batch_id' => $this->batch->id,
            'session' => $this->batch->academicSession->name,
            'total_students' => $this->batch->total_students,
            'url' => route('promotions.review', $this->batch),
            'message' => "Promotion batch ready for approval",
        ];
    }
}