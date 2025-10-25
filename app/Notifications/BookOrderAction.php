<?php

namespace App\Notifications;

use App\Models\Resource\BookOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for book order actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., students, admins, teachers) via mail or database channels.
 *
 * @package App\Notifications
 */
class BookOrderAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $bookOrder;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param BookOrder $bookOrder The book order.
     * @param string $action The action performed (create, update, delete, restore).
     */
    public function __construct(BookOrder $bookOrder, string $action = 'created')
    {
        $this->bookOrder = $bookOrder;
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
        $subject = "Book Order {$action}";
        $bookTitle = $this->bookOrder->book->title;
        $studentName = $this->bookOrder->student->full_name;

        return (new MailMessage)
            ->subject($subject)
            ->line("A book order has been {$this->action}.")
            ->line("Book: {$bookTitle}")
            ->line("Student: {$studentName}")
            ->line("Status: {$this->bookOrder->status}")
            ->line("Order Date: {$this->bookOrder->order_date->format('Y-m-d')}")
            ->when($this->bookOrder->return_date, function ($message) {
                $message->line("Return Date: {$this->bookOrder->return_date->format('Y-m-d')}");
            })
            ->action('View Book Order', route('book-orders.show', $this->bookOrder->id))
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
            'book_order_id' => $this->bookOrder->id,
            'book_list_id' => $this->bookOrder->book_list_id,
            'student_id' => $this->bookOrder->student_id,
            'action' => $this->action,
            'message' => "Book order {$this->action} for {$this->bookOrder->book->title} by {$this->bookOrder->student->full_name}.",
        ];
    }
}
