<?php

namespace App\Notifications;

use App\Models\Resource\LessonPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for lesson plan actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class LessonPlanAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $lessonPlan;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param LessonPlan $lessonPlan The lesson plan.
     * @param string $action The action performed (create, update, delete, restore).
     */
    public function __construct(LessonPlan $lessonPlan, string $action = 'created')
    {
        $this->lessonPlan = $lessonPlan;
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
        $subject = "Lesson Plan {$action}";
        $topic = $this->lessonPlan->topic;
        $classLevel = $this->lessonPlan->classLevel->name;
        $subjectName = $this->lessonPlan->subject->name;
        $staffName = $this->lessonPlan->staff->full_name;

        return (new MailMessage)
            ->subject($subject)
            ->line("A lesson plan has been {$this->action}.")
            ->line("Topic: {$topic}")
            ->line("Class Level: {$classLevel}")
            ->line("Subject: {$subjectName}")
            ->line("Teacher: {$staffName}")
            ->line("Date: {$this->lessonPlan->date->format('Y-m-d')}")
            ->action('View Lesson Plan', route('lesson-plans.show', $this->lessonPlan->id))
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
            'lesson_plan_id' => $this->lessonPlan->id,
            'action' => $this->action,
            'message' => "Lesson plan {$this->action} for {$this->lessonPlan->topic} in {$this->lessonPlan->classLevel->name}.",
        ];
    }
}
