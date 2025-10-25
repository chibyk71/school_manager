<?php

namespace App\Notifications;

use App\Models\Finance\FeeConcession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeConcessionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $feeConcession;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param FeeConcession $feeConcession
     * @param string $action
     */
    public function __construct(FeeConcession $feeConcession, string $action)
    {
        $this->feeConcession = $feeConcession;
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
            'message' => "A fee concession has been {$this->action}: {$this->feeConcession->name} (Type: {$this->feeConcession->type}, Amount: {$this->feeConcession->amount})",
            'fee_concession_id' => $this->feeConcession->id,
            'school_id' => $this->feeConcession->school_id,
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
            ->subject("Fee Concession {$this->action} Notification")
            ->line("A fee concession has been {$this->action}.")
            ->line("Name: {$this->feeConcession->name}")
            ->line("Type: {$this->feeConcession->type}")
            ->line("Amount: {$this->feeConcession->amount}")
            ->line("Start Date: {$this->feeConcession->start_date?->format('Y-m-d')}")
            ->line("End Date: {$this->feeConcession->end_date?->format('Y-m-d')}")
            ->action('View Fee Concession', route('fee-concessions.show', $this->feeConcession->id));
    }
}