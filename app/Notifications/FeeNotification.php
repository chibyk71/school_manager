<?php

namespace App\Notifications;

use App\Models\Finance\Fee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $fee;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param Fee $fee
     * @param string $action
     */
    public function __construct(Fee $fee, string $action)
    {
        $this->fee = $fee;
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @param mixed $notifiable
     * @return DatabaseMessage
     */
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'message' => "A fee has been {$this->action}: {$this->fee->description} (Amount: {$this->fee->amount}, Due: {$this->fee->due_date->format('Y-m-d')})",
            'fee_id' => $this->fee->id,
            'school_id' => $this->fee->school_id,
            'action' => $this->action,
        ]);
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Fee {$this->action} Notification")
            ->line("A fee has been {$this->action}.")
            ->line("Description: {$this->fee->description}")
            ->line("Amount: {$this->fee->amount}")
            ->line("Due Date: {$this->fee->due_date->format('Y-m-d')}")
            ->action('View Fee', route('fees.show', $this->fee->id));
    }
}