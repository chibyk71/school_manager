<?php

namespace App\Notifications;

use App\Models\Academic\TimeTable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification: TimeTableGeneratedNotification
 *
 * Notifies users (e.g., school admins or users with timetable permissions) when a draft timetable is generated.
 * Delivered via email, database, and broadcast channels, providing details about the timetable and a link to review it.
 *
 * Features:
 * - Tenant-scoped with school context in the message.
 * - Includes timetable title, term name, and school name for clarity.
 * - Provides an Inertia.js-compatible URL for reviewing the timetable.
 * - Queued for performance in multi-tenant environments.
 * - Logs failures for debugging.
 *
 * Prerequisites:
 * - Requires a valid TimeTable model instance with loaded term and school relationships.
 * - Assumes Inertia.js routes (e.g., timetables.show) are defined.
 */
class TimeTableGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The TimeTable instance.
     *
     * @var TimeTable
     */
    protected $timeTable;

    /**
     * Create a new notification instance.
     *
     * @param TimeTable $timeTable
     */
    public function __construct(TimeTable $timeTable)
    {
        $this->timeTable = $timeTable;
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array<string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        try {
            $school = GetSchoolModel();
            $termName = $this->timeTable->term ? $this->timeTable->term->name : 'Unknown Term';
            $schoolName = $school->name ?? 'Unknown School';

            return (new MailMessage)
                ->subject('Draft Timetable Generated')
                ->greeting("Hello {$notifiable->name},")
                ->line("A draft timetable '{$this->timeTable->title}' has been generated for the term: {$termName} at {$schoolName}.")
                ->line('Please review the timetable and make necessary adjustments before approval.')
                ->action('Review Timetable', route('timetables.show', ['timetable' => $this->timeTable->id]))
                ->line('Thank you for using our system!');
        } catch (\Exception $e) {
            Log::error("Failed to generate mail notification for timetable ID {$this->timeTable->id}: {$e->getMessage()}");
            return (new MailMessage)
                ->subject('Draft Timetable Generated')
                ->greeting("Hello {$notifiable->name},")
                ->line("A draft timetable has been generated.")
                ->line('Please check the system for details.')
                ->line('Thank you for using our system!');
        }
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        try {
            $termName = $this->timeTable->term ? $this->timeTable->term->name : 'Unknown Term';
            $school = GetSchoolModel();
            $schoolName = $school->name ?? 'Unknown School';

            return [
                'time_table_id' => $this->timeTable->id,
                'title' => $this->timeTable->title,
                'term_name' => $termName,
                'school_name' => $schoolName,
                'message' => "A draft timetable '{$this->timeTable->title}' has been generated for the term: {$termName} at {$schoolName}.",
                'url' => route('timetables.show', ['timetable' => $this->timeTable->id]),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to generate array notification for timetable ID {$this->timeTable->id}: {$e->getMessage()}");
            return [
                'time_table_id' => $this->timeTable->id,
                'message' => 'A draft timetable has been generated.',
                'url' => route('timetables.index'),
            ];
        }
    }

    /**
     * Get the broadcast representation of the notification.
     *
     * @param mixed $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        try {
            $termName = $this->timeTable->term ? $this->timeTable->term->name : 'Unknown Term';
            $school = GetSchoolModel();
            $schoolName = $school->name ?? 'Unknown School';

            return new BroadcastMessage([
                'time_table_id' => $this->timeTable->id,
                'title' => $this->timeTable->title,
                'term_name' => $termName,
                'school_name' => $schoolName,
                'message' => "A draft timetable '{$this->timeTable->title}' has been generated for the term: {$termName} at {$schoolName}.",
                'url' => route('timetables.show', ['timetable' => $this->timeTable->id]),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to generate broadcast notification for timetable ID {$this->timeTable->id}: {$e->getMessage()}");
            return new BroadcastMessage([
                'time_table_id' => $this->timeTable->id,
                'message' => 'A draft timetable has been generated.',
                'url' => route('timetables.index'),
            ]);
        }
    }
}