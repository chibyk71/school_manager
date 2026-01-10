<?php

namespace App\Notifications;

use App\Models\Academic\Term;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * TermClosedNotification – Notifies relevant users when an academic term is closed
 *
 * Triggered automatically when a term is successfully closed via TermClosureService.
 * This notification informs key school personnel (admins, principal, class teachers,
 * academic officers, etc.) that the term has ended, marking the point for:
 *   - End-of-term result finalization
 *   - Report card generation (future integration)
 *   - Student performance review
 *   - Transition to next term
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Multi-channel delivery: database (in-app bell), email, broadcast (real-time)
 * • Queued by default → prevents blocking the closure request/response cycle
 * • Multi-tenant safe: content scoped to the specific term and its parent session
 * • Professional, informative email with key term/session details + action link
 * • Markdown formatting for clean, readable email layout
 * • Real-time broadcast payload optimized for PrimeVue Toast/Notification center
 * • Audit-friendly: includes timestamps, term/session context
 * • Easy to extend: add SMS, push notifications, or role-specific filtering
 * • Supports future downstream actions (e.g. auto-generate reports on closure)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Dispatched from TermClosureService::closeTerm()
 * • Part of the term closure workflow:
 *     1. Admin/Principal initiates closure → service validates → updates status
 *     2. Service fires TermClosed event
 *     3. Event listener (or direct call) sends this notification
 * • Complements TermClosed event for loose coupling with future modules
 *   (e.g. ReportCards module listens for end-of-term processing)
 * • Aligns with multi-tenant design: only notifies users of the same school
 *
 * Recommended Usage (Event Listener Example):
 *   // In EventServiceProvider or dedicated listener
 *   use App\Events\Academic\TermClosed;
 *   use App\Notifications\TermClosedNotification;
 *
 *   public function handle(TermClosed $event)
 *   {
 *       $users = $event->term->school->users()
 *           ->whereHas('roles', fn($q) => $q->whereIn('name', [
 *               'admin', 'principal', 'class-teacher', 'academic-officer'
 *           ]))
 *           ->get();
 *
 *       $users->each->notify(new TermClosedNotification($event->term));
 *   }
 */
class TermClosedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Term $term;

    /**
     * Create a new notification instance.
     */
    public function __construct(Term $term)
    {
        $this->term = $term;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Get the database representation (for in-app notification center / bell icon).
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'term_id'       => $this->term->id,
            'term_name'     => $this->term->display_name,
            'session_name'  => $this->term->academicSession->name,
            'message'       => "The term {$this->term->display_name} in {$this->term->academicSession->name} has been closed.",
            'icon'          => 'pi pi-calendar-minus',
            'color'         => 'warning',
            'severity'      => 'warn',
            'action_url'    => route('terms.show', $this->term->id),
            'action_label'  => 'View Term Details',
            'closed_at'     => $this->term->closed_at?->toDateTimeString(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->term->academicSession;

        return (new MailMessage)
            ->subject("Term Closed: {$this->term->display_name} ({$session->name})")
            ->greeting("Dear {$notifiable->name},")
            ->line("The term **{$this->term->display_name}** in the academic session **{$session->name}** has been officially closed.")
            ->line("This marks the end of academic activities for this term.")
            ->line("Key Dates:")
            ->line("• Term Start: **{$this->term->start_date?->format('F d, Y')}**")
            ->line("• Term End: **{$this->term->end_date?->format('F d, Y')}**")
            ->line("• Closed On: **{$this->term->closed_at?->format('F d, Y H:i')}**")
            ->action('View Term Details', route('terms.show', $this->term->id))
            ->line('Next steps typically include finalizing results, generating report cards, and preparing for the next term.')
            ->line('Please contact the academic office if you have any questions or require support.')
            ->salutation('Best regards,' . "\n" . config('app.name') . ' Team');
    }

    /**
     * Get the broadcast (real-time) representation of the notification.
     * Used for immediate UI feedback (e.g. PrimeVue Toast or notification center).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title'       => 'Term Closed',
            'message'     => "Term {$this->term->display_name} ({$this->term->academicSession->name}) has been closed.",
            'icon'        => 'pi pi-calendar-minus',
            'severity'    => 'warn',
            'url'         => route('terms.show', $this->term->id),
            'time'        => now()->toDateTimeString(),
        ]);
    }

    /**
     * Optional: Customize channel connections if needed
     */
    public function viaConnections(): array
    {
        return [
            'database'  => 'database',
            'mail'      => 'mail',
            'broadcast' => 'pusher', // or 'reverb', 'echo', etc. — match your broadcast driver
        ];
    }
}
