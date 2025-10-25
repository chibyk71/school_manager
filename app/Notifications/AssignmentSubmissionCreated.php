<?php

namespace App\Notifications;

use App\Models\Resource\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for assignment submission actions (create, update, delete).
 *
 * Sends notifications to students and/or teachers via mail or database channels.
 *
 * @package App\Notifications
 */
class AssignmentSubmissionCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $submission;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param AssignmentSubmission $submission The assignment submission.
     * @param string $action The action performed (create, update, delete).
     */
    public function __construct(AssignmentSubmission $submission, string $action = 'created')
    {
        $this->submission = $submission;
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
        $subject = "Assignment Submission {$action}";
        $assignmentTitle = $this->submission->assignment->title;
        $studentName = $this->submission->student->full_name;

        return (new MailMessage)
            ->subject($subject)
            ->line("An assignment submission has been {$this->action}.")
            ->line("Assignment: {$assignmentTitle}")
            ->line("Student: {$studentName}")
            ->line("Status: {$this->submission->status}")
            ->when($this->submission->mark_obtained !== null, function ($message) {
                $message->line("Mark Obtained: {$this->submission->mark_obtained}");
            })
            ->when($this->submission->remark, function ($message) {
                $message->line("Remark: {$this->submission->remark}");
            })
            ->action('View Submission', route('assignment-submissions.show', $this->submission->id))
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
            'submission_id' => $this->submission->id,
            'assignment_id' => $this->submission->assignment_id,
            'student_id' => $this->submission->student_id,
            'action' => $this->action,
            'message' => "Assignment submission {$this->action} for {$this->submission->assignment->title} by {$this->submission->student->full_name}.",
        ];
    }
}