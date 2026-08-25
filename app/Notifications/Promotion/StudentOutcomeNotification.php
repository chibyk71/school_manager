<?php

namespace App\Notifications\Promotion;

use App\Models\Promotion\PromotionHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * StudentOutcomeNotification
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Sent to the **student** (or their parent/guardian) with their final promotion outcome.
 *
 * This is the most visible notification to end-users (students/parents).
 *
 * Purpose:
 * - Inform student of their final promotion decision (promote / repeat / graduate)
 * - Include key details (average score, next class if applicable)
 * - Support database + mail + SMS channels
 * - Triggered per student after successful execution
 *
 * Used by: ProcessStudentPromotion job (or NotifyOnBatchCompleted)
 */

class StudentOutcomeNotification extends Notification
{
    use Queueable;

    public PromotionHistory $history;

    /**
     * Create a new notification instance.
     */
    public function __construct(PromotionHistory $history)
    {
        $this->history = $history;
    }

    /**
     * Get the notification's delivery channels.
     *
     * Students usually prefer SMS + Mail for important academic outcomes.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
        // Add 'sms' when SmsService integration is ready
    }

    /**
     * Get the array representation for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_promotion_outcome',
            'history_id' => $this->history->id,
            'outcome' => $this->history->outcome,
            'outcome_label' => $this->history->outcome_label,
            'from_session' => $this->history->fromAcademicSession?->name,
            'to_session' => $this->history->toAcademicSession?->name,
            'average_score' => $this->history->average_score,
            'message' => "Your promotion outcome for {$this->history->fromAcademicSession?->name} is: {$this->history->outcome_label}",
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $outcome = $this->history->outcome_label;
        $session = $this->history->fromAcademicSession?->name;

        return (new MailMessage)
            ->subject("Your Promotion Outcome - {$session}")
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line("We are pleased to inform you of your promotion outcome for the academic session **{$session}**.")
            ->line("**Outcome:** {$outcome}")
            ->when($this->history->average_score, fn($mail) => 
                $mail->line("Average Score: **{$this->history->average_score}**")
            )
            ->when($this->history->toClassSection, fn($mail) => 
                $mail->line("Next Class: **" . ($this->history->toClassSection->full_name ?? $this->history->toClassSection->name) . "**")
            )
            ->line('Congratulations!' . ($this->history->outcome === 'repeat' ? ' You will repeat the class.' : ''))
            ->action('View Full Details', route('students.promotion-history', $this->history->student_id));
    }
}    