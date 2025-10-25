<?php

namespace App\Notifications;

use App\Models\Resource\Syllabus;
use App\Models\Resource\SyllabusApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for syllabus approval actions (submit, approve, reject).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class SyllabusApprovalAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $syllabus;
    protected $approval;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param Syllabus $syllabus The syllabus.
     * @param SyllabusApproval $approval The approval request.
     * @param string $action The action performed (submitted, approved, rejected).
     */
    public function __construct(Syllabus $syllabus, SyllabusApproval $approval, string $action = 'submitted')
    {
        $this->syllabus = $syllabus;
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
        $subject = "Syllabus Approval {$action}";
        $topic = $this->syllabus->topic;
        $subjectName = $this->syllabus->subject->name;
        $classLevel = $this->syllabus->classLevel->name;
        $term = $this->syllabus->term->name;
        $comments = $this->approval->comments ?? 'No comments provided';

        return (new MailMessage)
            ->subject($subject)
            ->line("A syllabus has been {$this->action} for approval.")
            ->line("Topic: {$topic}")
            ->line("Subject: {$subjectName}")
            ->line("Class Level: {$classLevel}")
            ->line("Term: {$term}")
            ->line("Status: {$this->syllabus->status}")
            ->line("Comments: {$comments}")
            ->action('View Syllabus', route('syllabi.show', $this->syllabus->id))
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
            'syllabus_id' => $this->syllabus->id,
            'approval_id' => $this->approval->id,
            'action' => $this->action,
            'message' => "Syllabus {$this->action} for approval: {$this->syllabus->topic} in {$this->syllabus->subject->name}.",
        ];
    }
}
