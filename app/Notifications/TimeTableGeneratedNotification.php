<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TimeTableGeneratedNotification extends Notification
{
    use Queueable;

    protected $timeTable;

    /**
     * Create a new notification instance.
     */
    public function __construct($timeTable)
    {
        $this->timetable = $timeTable;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Draft Timetable Generated')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A draft timetable has been successfully generated for the term: ' . $this->timeTable->term->name . '.')
            ->line('You can review the timetable and make necessary adjustments before approval.')
            ->action('Review Timetable', url('/timetables/' . $this->timeTable->id))
            ->line('Thank you for using our system!');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'time_table_id' => $this->timeTable->id,
            'term_name' => $this->timeTable->term->name,
            'message' => 'A draft timetable has been generated for the term: ' . $this->timeTable->term->name . '.',
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     *
     * @param mixed $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'time_table_id' => $this->timeTable->id,
            'term_name' => $this->timeTable->term->name,
            'message' => 'A draft timetable has been generated for the term: ' . $this->timeTable->term->name . '.',
        ]);
    }
}
