<?php

namespace App\Notifications;

use App\Models\Resource\SyllabusDetail;
use App\Models\Resource\SyllabusDetailApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for syllabus detail approval actions (submit, approve, reject).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class SyllabusDetailApprovalAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $syllabusDetail;
    protected $approval;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param SyllabusDetail $syllabusDetail The syllabus detail.
     * @param SyllabusDetailApproval $approval The approval request.
     * @param string $action The action performed (submitted, approved, rejected).
     */
    public function __construct(SyllabusDetail $syllabusDetail, SyllabusDetailApproval $approval, string $action = 'submitted')
    {
        $this->syllabusDetail = $syllabusDetail;
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
        $subject = "Syllabus Detail Approval {$action}";
        $topic = $this->syllabusDetail->topic;
        $syllabusTopic = $this->syllabusDetail->syllabus->topic;
        $week = $this->syllabusDetail->week;
        $comments = $this->approval->comments ?? 'No comments provided';

        return (new MailMessage)
            ->subject($subject)
            ->line("A syllabus detail has been {$this->action} for approval.")
            ->line("Topic: {$topic}")
            ->line("Syllabus: {$syllabusTopic}")
            ->line("Week: {$week}")
            ->line("Status: {$this->syllabusDetail->status}")
            ->line("Comments: {$comments}")
            ->action('View Syllabus Detail', route('syllabus-details.show', $this->syllabusDetail->id))
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
            'syllabus_detail_id' => $this->syllabusDetail->id,
            'approval_id' => $this->approval->id,
            'action' => $this->action,
            'message' => "Syllabus detail {$this->action} for approval: {$this->syllabusDetail->topic} in week {$this->syllabusDetail->week}.",
        ];
    }
}
