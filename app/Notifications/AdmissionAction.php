<?php

namespace App\Notifications;

use App\Models\Student\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for admission actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., staff, admins) via mail or database channels.
 *
 * @package App\Notifications
 */
class AdmissionAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $admission;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param Admission $admission The admission record.
     * @param string $action The action performed (created, updated, deleted, restored).
     */
    public function __construct(Admission $admission, string $action = 'created')
    {
        $this->admission = $admission;
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
        $subject = "Admission {$action}";
        $rollNo = $this->admission->roll_no;
        $student = $this->admission->student->full_name ?? 'Unknown';
        $classLevel = $this->admission->classLevel->name ?? 'Unknown';
        $status = $this->admission->status;

        return (new MailMessage)
            ->subject($subject)
            ->line("An admission has been {$this->action}.")
            ->line("Roll Number: {$rollNo}")
            ->line("Student: {$student}")
            ->line("Class Level: {$classLevel}")
            ->line("Status: {$status}")
            ->action('View Admission', route('admissions.show', $this->admission->id))
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
            'admission_id' => $this->admission->id,
            'action' => $this->action,
            'message' => "Admission {$this->action}: {$this->admission->roll_no} for {$this->admission->student->full_name}.",
        ];
    }
}
