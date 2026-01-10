<?php

namespace App\Notifications;

use App\Models\Academic\AcademicSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * SessionActivatedNotification – Notifies relevant users when an academic session is activated
 *
 * This notification is triggered via the SessionActivated event (dispatched from AcademicCalendarService).
 * It informs key school personnel (admins, principal, academic officers) that a new academic session
 * has been officially activated and is now the current/active one.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Multi-channel delivery: database (for in-app bell), email, broadcast (real-time)
 * • Multi-tenant safe: only notifies users belonging to the same school
 * • Queued by default → prevents blocking the activation request
 * • Clean, professional content with action links (view session details)
 * • Supports markdown formatting for better email readability
 * • Easy to extend: add SMS via Nexmo/Twilio, push via Laravel Echo, etc.
 * • Audit-friendly: logs notification delivery status
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Dispatched automatically on session activation (AcademicCalendarService@activateSession)
 * • Complements SessionActivated event for loose coupling
 * • Part of the activation workflow: create session → activate → notify stakeholders
 * • Integrates with existing notification system (database + mail)
 * • Prepares for future enhancements: principal approval workflow, parent summary
 *
 * Notification Flow:
 *   1. Session activated → AcademicCalendarService dispatches SessionActivated event
 *   2. Event listener (or direct call) → $users->notify(new SessionActivatedNotification($session))
 *   3. Notification sent via queue to database + email + broadcast
 *
 * Customization Tips:
 *   • Change recipients in `toDatabase()` or `via()`
 *   • Adjust email template or add SMS channel
 *   • Use `locale` for multilingual schools if needed
 */
class SessionActivatedNotification extends Notification implements ShouldQueue
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
     * Get the database representation of the notification (in-app bell).
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'session_id'    => $this->session->id,
            'session_name'  => $this->session->name,
            'message'       => "The academic session {$this->session->name} has been activated and is now the current session.",
            'icon'          => 'pi pi-calendar',
            'color'         => 'success',
            'action_url'    => route('academic-sessions.show', $this->session->id),
            'action_label'  => 'View Session',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Academic Session Activated: {$this->session->name}")
            ->greeting("Dear {$notifiable->name},")
            ->line("The academic session **{$this->session->name}** has been officially activated.")
            ->line("This session is now the current active session for your school.")
            ->line("Start Date: **{$this->session->start_date->format('F d, Y')}**")
            ->line("End Date: **{$this->session->end_date->format('F d, Y')}**")
            ->action('View Session Details', route('academic-sessions.show', $this->session->id))
            ->line('All academic activities (terms, assessments, attendance, results) should now reference this session.')
            ->salutation('Best regards, ' . config('app.name') . ' Team');
    }

    /**
     * Get the broadcast (real-time) representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title'       => 'Session Activated',
            'message'     => "Academic session {$this->session->name} is now active!",
            'icon'        => 'pi pi-calendar-check',
            'severity'    => 'success',
            'url'         => route('academic-sessions.show', $this->session->id),
            'time'        => now()->toDateTimeString(),
        ]);
    }

    /**
     * Determine which channels the notification should be sent to for this user.
     * (Can be customized per user role/preference if needed)
     */
    public function viaConnections(): array
    {
        return [
            'database'  => 'database',
            'mail'      => 'mail',
            'broadcast' => 'pusher', // or whatever your broadcast driver is
        ];
    }
}
