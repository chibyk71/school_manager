<?php

namespace App\Notifications;

use App\Models\Finance\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transaction;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param Transaction $transaction
     * @param string $action
     */
    public function __construct(Transaction $transaction, string $action)
    {
        $this->transaction = $transaction;
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
            'message' => "A transaction has been {$this->action}: {$this->transaction->category} (Amount: {$this->transaction->amount}, Date: {$this->transaction->transaction_date->format('Y-m-d')})",
            'transaction_id' => $this->transaction->id,
            'school_id' => $this->transaction->school_id,
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
            ->subject("Transaction {$this->action} Notification")
            ->line("A transaction has been {$this->action}.")
            ->line("Category: {$this->transaction->category}")
            ->line("Amount: {$this->transaction->amount}")
            ->line("Date: {$this->transaction->transaction_date->format('Y-m-d')}")
            ->action('View Transaction', route('transactions.show', $this->transaction->id));
    }
}