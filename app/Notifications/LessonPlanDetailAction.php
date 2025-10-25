<?php

namespace App\Notifications;

use App\Models\Resource\LessonPlanDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for lesson plan detail actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class LessonPlanDetailAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $lessonPlanDetail;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param LessonPlanDetail $lessonPlanDetail The lesson plan detail.
     * @param string $action The action performed (create, update, delete, restore).
     */
    public function __construct(LessonPlanDetail $lessonPlanDetail, string $action = 'created')
    {
        $this->lessonPlanDetail = $lessonPlanDetail;
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
        $subject = "Lesson Plan Detail {$action}";
        $title = $this->lessonPlanDetail->title;
        $lessonPlanTopic = $this->lessonPlanDetail->lessonPlan->topic;

        return (new MailMessage)
            ->subject($subject)
            ->line("A lesson plan detail has been {$this->action}.")
            ->line("Title: {$title}")
            ->line("Lesson Plan: {$lessonPlanTopic}")
            ->line("Status: {$this->lessonPlanDetail->status}")
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
            'lesson_plan_id' => $this->lessonPlanDetail->lesson_plan_id,
            'action' => $this->action,
            'message' => "Lesson plan detail {$this->action} for {$this->lessonPlanDetail->title} in lesson plan {$this->lessonPlanDetail->lessonPlan->topic}.",
        ];
    }
}
