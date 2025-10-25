<?php

namespace App\Notifications;

use App\Models\Resource\BookList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for book list actions (create, update, delete, restore).
 *
 * Sends notifications to relevant users (e.g., admins, teachers) via mail or database channels.
 *
 * @package App\Notifications
 */
class BookListAction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $bookList;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param BookList $bookList The book list entry.
     * @param string $action The action performed (create, update, delete, restore).
     */
    public function __construct(BookList $bookList, string $action = 'created')
    {
        $this->bookList = $bookList;
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
        $subject = "Book List {$action}";
        $title = $this->bookList->title;
        $classLevel = $this->bookList->classLevel->name;
        $subjectName = $this->bookList->subject->name;

        return (new MailMessage)
            ->subject($subject)
            ->line("A book list entry has been {$this->action}.")
            ->line("Book Title: {$title}")
            ->line("Class Level: {$classLevel}")
            ->line("Subject: {$subjectName}")
            ->when($this->bookList->price !== null, function ($message) {
                $message->line("Price: {$this->bookList->price}");
            })
            ->action('View Book List', route('book-lists.show', $this->bookList->id))
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
            'book_list_id' => $this->bookList->id,
            'action' => $this->action,
            'message' => "Book list entry {$this->action} for {$this->bookList->title} in {$this->bookList->classLevel->name}.",
        ];
    }
}