<?php

namespace App\Notifications;

use App\Models\Resource\Syllabus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for syllabus actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class SyllabusAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $syllabus;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param Syllabus $syllabus The syllabus.
     * @param string $action The action performed (create, update, delete, restore).
     */
    public function __construct(Syllabus $syllabus, string $action = 'created')
    {
        $this->syllabus = $syllabus;
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
        $subject = "Syllabus {$action}";
        $topic = $this->syllabus->topic;
        $subjectName = $this->syllabus->subject->name;
        $classLevel = $this->syllabus->classLevel->name;
        $term = $this->syllabus->term->name;

        return (new MailMessage)
            ->subject($subject)
            ->line("A syllabus has been {$this->action}.")
            ->line("Topic: {$topic}")
            ->line("Subject: {$subjectName}")
            ->line("Class Level: {$classLevel}")
            ->line("Term: {$term}")
            ->line("Status: {$this->syllabus->status}")
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
            'action' => $this->action,
            'message' => "Syllabus {$this->action} for {$this->syllabus->topic} in {$this->syllabus->subject->name}.",
        ];
    }
}
