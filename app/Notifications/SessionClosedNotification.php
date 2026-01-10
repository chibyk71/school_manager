<?php

namespace App\Notifications;

use App\Models\Academic\AcademicSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * SessionClosedNotification – Notifies relevant users when an academic session is closed
 *
 * Triggered automatically when a session is successfully closed via AcademicCalendarService.
 * This notification informs key school personnel (admins, principal, academic officers, etc.)
 * that the session has ended, marking the transition point for actions like:
 *   - Annual result finalization
 *   - Promotion/repetition decisions (future module)
 *   - Report card generation
 *   - Historical archiving
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Multi-channel delivery: database (in-app notifications), email, broadcast (real-time)
 * • Queued by default → prevents blocking the closure request/response
 * • Multi-tenant safe: content is scoped to the specific session and school
 * • Professional, informative email with key session details and action link
 * • Markdown support for clean, readable email formatting
 * • Real-time broadcast message for immediate UI feedback (PrimeVue Toast/Notification center)
 * • Audit-friendly: includes timestamps and session context
 * • Easy to extend: add SMS, push notifications, or role-specific filtering
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Dispatched from AcademicCalendarService::closeSession()
 * • Part of the session closure workflow:
 *     1. Admin/Principal closes session → service validates → updates status
 *     2. Service fires SessionClosed event
 *     3. Event listener (or direct call) sends this notification
 * • Serves as a trigger point for downstream processes (promotion, archiving)
 * • Aligns with multi-tenant design: only notifies users of the same school
 *
 * Recommended Usage:
 *   // In event listener for SessionClosed event
 *   $users = $event->session->school->users()
 *       ->whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'principal', 'academic-officer']))
 *       ->get();
 *   $users->each->notify(new SessionClosedNotification($event->session));
 */
class SessionClosedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public AcademicSession $session;

    /**
     * Create a new notification instance.
     */
    public function __construct(AcademicSession $session)
    {
        $this->session = $session;
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
            'session_id' => $this->session->id,
            'session_name' => $this->session->name,
            'message' => "The academic session {$this->session->name} has been closed.",
            'icon' => 'pi pi-calendar-times',
            'color' => 'warning',
            'severity' => 'warn',
            'action_url' => route('academic-sessions.show', $this->session->id),
            'action_label' => 'View Session Details',
            'closed_at' => $this->session->closed_at?->toDateTimeString(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Academic Session Closed: {$this->session->name}")
            ->greeting("Dear {$notifiable->name},")
            ->line("The academic session **{$this->session->name}** has been officially closed.")
            ->line("This marks the end of academic activities for this session.")
            ->line("Key Dates:")
            ->line("• Start Date: **{$this->session->start_date->format('F d, Y')}**")
            ->line("• End Date: **{$this->session->end_date->format('F d, Y')}**")
            ->line("• Closed On: **{$this->session->closed_at->format('F d, Y H:i')}**")
            ->action('Review Session Details', route('academic-sessions.show', $this->session->id))
            ->line('Next steps may include finalizing results, processing promotions, and preparing reports.')
            ->line('Please contact the academic office if you have any questions.')
            ->salutation('Best regards,' . "\n" . config('app.name') . ' Team');
    }

    /**
     * Get the broadcast (real-time) representation of the notification.
     * Used for immediate UI feedback (e.g. PrimeVue Toast or notification center).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Session Closed',
            'message' => "Academic session {$this->session->name} has been closed.",
            'icon' => 'pi pi-calendar-times',
            'severity' => 'warn',
            'url' => route('academic-sessions.show', $this->session->id),
            'time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Optional: Customize channel connections (e.g., specific mailer, broadcast driver)
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'database',
            'mail' => 'mail',
            'broadcast' => 'pusher', // or 'reverb', 'echo', etc. – match your config
        ];
    }
}
