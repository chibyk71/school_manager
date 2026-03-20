<?php

namespace App\Notifications\SchoolSection;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SectionDeactivatedNotification
 *
 * Notifies school administrators when one or more sections are deactivated,
 * informing them of the downstream effects on class levels and enrollments.
 *
 * ── When It Is Sent ───────────────────────────────────────────────────────
 * Dispatched by NotifyAdminOnSectionDeactivated listener, which listens
 * to the SchoolSectionDeactivated domain event fired by
 * SchoolSectionService::bulkToggleStatus() when is_active = false.
 *
 * ── Why Admins Need This ─────────────────────────────────────────────────
 * Deactivating a section triggers a cascade via CascadeDeactivateClassLevels
 * listener — all class levels under the section are also deactivated.
 * Admins need to know:
 *   1. Which sections were deactivated
 *   2. That class levels were also deactivated (downstream effect)
 *   3. That existing role assignments scoped to those sections still exist
 *      but users will effectively lose scoped access while section is inactive
 *
 * ── Channels ─────────────────────────────────────────────────────────────
 * database: stored in notifications table, shown in app notification bell.
 *           Dismissed manually by the admin.
 * mail:     Markdown formatted email sent to the admin's email address.
 *           Queued automatically by Laravel's mail channel.
 *
 * ── Payload ──────────────────────────────────────────────────────────────
 * $sections: Collection of deactivated SchoolSection instances.
 * Passed from the event → listener → notification to avoid re-querying.
 *
 * ── Database Shape ───────────────────────────────────────────────────────
 * toArray() returns a structured array stored in notifications.data column.
 * Frontend notification bell reads: type, title, body, section_names,
 * section_ids, count, action_url.
 *
 * ── Mail Template ────────────────────────────────────────────────────────
 * Uses Laravel Markdown mail. Template file:
 *   resources/views/emails/school-section/deactivated.blade.php
 * Create this view with @component('mail::message') to customise layout.
 * Falls back to toMail() inline content if view is not yet created.
 *
 * @see App\Listeners\SchoolSection\NotifyAdminOnSectionDeactivated
 * @see App\Events\SchoolSection\SchoolSectionDeactivated
 */
class SectionDeactivatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Collection $sections
    ) {}

    /**
     * Delivery channels for this notification.
     *
     * @param  mixed  $notifiable
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Build the mail representation.
     *
     * Personalises the greeting using the notifiable's name.
     * Lists each deactivated section with its display_name and short_code.
     * Links to the sections index so admin can act immediately.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $recipientName = $notifiable->full_name
            ?? $notifiable->name
            ?? 'Administrator';

        $count        = $this->sections->count();
        $sectionLabel = $count === 1 ? 'section' : 'sections';
        $schoolName   = $this->sections->first()?->school?->name ?? 'your school';

        return (new MailMessage)
            ->subject("School Section Deactivated — {$schoolName}")
            ->markdown('emails.school-section.deactivated', [
                'recipientName' => $recipientName,
                'sections'      => $this->sections,
                'count'         => $count,
                'sectionLabel'  => $sectionLabel,
                'schoolName'    => $schoolName,
                'actionUrl'     => route('settings.sections.index'),
            ]);
    }

    /**
     * Build the database representation.
     *
     * Stored in notifications.data as JSON.
     * Frontend reads type, title, body for notification bell rendering.
     * section_ids allows deep-linking to specific sections if needed.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $count        = $this->sections->count();
        $sectionLabel = $count === 1 ? 'section' : 'sections';
        $names        = $this->sections->pluck('display_name')->join(', ', ' and ');

        return [
            'type'          => 'section_deactivated',
            'title'         => "School {$sectionLabel} deactivated",
            'body'          => $count === 1
                ? "The \"{$names}\" section has been deactivated. "
                  . 'Its class levels have also been deactivated automatically.'
                : "{$count} sections have been deactivated: {$names}. "
                  . 'Their class levels have also been deactivated automatically.',
            'section_names' => $this->sections->pluck('display_name')->toArray(),
            'section_ids'   => $this->sections->pluck('id')->toArray(),
            'count'         => $count,
            'action_url'    => route('settings.sections.index'),
            'action_label'  => 'View Sections',
        ];
    }
}
