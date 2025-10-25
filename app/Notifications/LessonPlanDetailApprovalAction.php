<?php

namespace App\Notifications;

use App\Models\Resource\LessonPlanDetail;
use App\Models\Resource\LessonPlanDetailApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for lesson plan detail approval actions (submit, approve, reject).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class LessonPlanDetailApprovalAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $lessonPlanDetail;
    protected $approval;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail.
     * @param LessonPlanDetailApproval $approval The approval request.
     * @param string $action The action performed (submitted, approved, rejected).
     */
    public function __construct(LessonPlanDetail $lessonPlanDetail, LessonPlanDetailApproval $approval, string $action = 'submitted')
    {
        $this->lessonPlanDetail = $lessonPlanDetail;
        $this->approval = $approval;
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array<string>
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $action = ucfirst($this->action);
        $subject = "Lesson Plan Detail Approval {$action}";
        $title = $this->lessonPlanDetail->title;
        $lessonPlanTopic = $this->lessonPlanDetail->lessonPlan->topic;
        $status = $this->lessonPlanDetail->status;
        $comments = $this->approval->comments ?? 'No comments provided';

        return (new MailMessage)
            ->subject($subject)
            ->line("A lesson plan detail has been {$this->action} for approval.")
            ->line("Title: {$title}")
            ->line("Lesson Plan: {$lessonPlanTopic}")
            ->line("Status: {$status}")
            ->line("Comments: {$comments}")
            ->action('View Lesson Plan Detail', route('lesson-plan-details.show', $this->lessonPlanDetail->id))
            ->line('Thank you for using our school management system!');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @param mixed $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return [
            'lesson_plan_detail_id' => $this->lessonPlanDetail->id,
            'approval_id' => $this->approval->id,
            'action' => $this->action,
            'message' => "Lesson plan detail {$this->action} for approval: {$this->lessonPlanDetail->title} in lesson plan {$this->lessonPlanDetail->lessonPlan->topic}.",
        ];
    }
}
