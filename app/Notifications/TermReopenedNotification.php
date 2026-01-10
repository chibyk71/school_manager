<?php

namespace App\Notifications;

use App\Models\Academic\Term;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * TermReopenedNotification – Notifies relevant users when a previously closed academic term is reopened
 *
 * This is a **restricted operation** triggered only when the most recently closed term
 * is reopened (via TermClosureService), typically to correct errors or extend deadlines.
 *
 * The notification alerts key personnel (admins, principal, class teachers, academic officers)
 * about this exceptional action, including audit trail information.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Multi-channel delivery: database (persistent in-app), email (formal record), broadcast (real-time alert)
 * • Queued by default → prevents blocking the reopen request/response
 * • Multi-tenant safe: scoped to the specific term and its parent session
 * • Professional email with warning tone (reopen is unusual/risky) + clear action link
 * • Markdown formatting for readable, structured email content
 * • Broadcast payload optimized for PrimeVue Toast / Notification center (warning severity)
 * • Audit-friendly: includes timestamps, user context (via event if available), term/session details
 * • Emphasizes that this is an exceptional action (helps reinforce proper usage)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Dispatched from TermClosureService::reopenTerm()
 * • Part of the rare term reopen workflow:
 *     1. Admin/Principal initiates reopen → service validates restrictions → updates status
 *     2. Service fires TermReopened event
 *     3. Event listener (or direct call) sends this notification
 * • Complements TermReopened event for loose coupling with future modules
 *   (e.g. audit logs, reverse report card generation if needed)
 * • Aligns with multi-tenant design: only notifies users of the same school
 *
 * Recommended Usage (Event Listener Example):
 *   // In EventServiceProvider or dedicated listener
 *   use App\Events\Academic\TermReopened;
 *   use App\Notifications\TermReopenedNotification;
 *
 *   public function handle(TermReopened $event)
 *   {
 *       $term = $event->term;
 *
 *       $users = $term->school->users()
 *           ->whereHas('roles', fn($q) => $q->whereIn('name', [
 *               'admin', 'principal', 'class-teacher', 'academic-officer'
 *           ]))
 *           ->get();
 *
 *       $users->each->notify(new TermReopenedNotification($term));
 *   }
 */
class TermReopenedNotification extends Notification implements ShouldQueue
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
            'term_id' => $this->term->id,
            'term_name' => $this->term->display_name,
            'session_name' => $this->term->academicSession->name,
            'message' => "The term {$this->term->display_name} in {$this->term->academicSession->name} has been REOPENED.",
            'icon' => 'pi pi-calendar-plus',
            'color' => 'info',
            'severity' => 'info',
            'action_url' => route('terms.show', $this->term->id),
            'action_label' => 'View Term Details',
            'reopened_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->term->academicSession;

        return (new MailMessage)
            ->subject("Term Reopened: {$this->term->display_name} ({$session->name})")
            ->greeting("Dear {$notifiable->name},")
            ->line("**Important Notice:** The term **{$this->term->display_name}** in the academic session **{$session->name}** has been **reopened**.")
            ->line("This is an exceptional action taken to correct records or extend deadlines.")
            ->line("Key Details:")
            ->line("• Term Start: **{$this->term->start_date?->format('F d, Y')}**")
            ->line("• Term End (current): **{$this->term->end_date?->format('F d, Y')}**")
            ->line("• Reopened On: **" . now()->format('F d, Y H:i') . "**")
            ->action('Review Term Details', route('terms.show', $this->term->id))
            ->line('Please verify that any previously finalized results, reports, or assessments are still accurate.')
            ->line('Contact the academic office immediately if you have concerns about this change.')
            ->salutation('Best regards,' . "\n" . config('app.name') . ' Team');
    }

    /**
     * Get the broadcast (real-time) representation of the notification.
     * Used for immediate UI feedback (e.g. PrimeVue Toast or notification center).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Term Reopened',
            'message' => "Term {$this->term->display_name} ({$this->term->academicSession->name}) has been reopened.",
            'icon' => 'pi pi-calendar-plus',
            'severity' => 'info',
            'url' => route('terms.show', $this->term->id),
            'time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Optional: Customize channel connections if needed
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'database',
            'mail' => 'mail',
            'broadcast' => 'pusher', // or 'reverb', 'echo', etc. — match your broadcast driver
        ];
    }
}
