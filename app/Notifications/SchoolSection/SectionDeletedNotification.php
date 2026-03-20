<?php

namespace App\Notifications\SchoolSection;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SectionDeletedNotification
 *
 * Notifies school administrators when one or more sections are soft-deleted,
 * warning them about orphaned Laratrust role assignments.
 *
 * ── When It Is Sent ───────────────────────────────────────────────────────
 * Dispatched by NotifyAdminOnSectionDeleted listener, which listens to
 * the SchoolSectionDeleted domain event fired by
 * SchoolSectionService::bulkDelete().
 *
 * ── Why Admins Need This ─────────────────────────────────────────────────
 * The service blocks deletion when class levels or enrolled students exist.
 * By the time this notification fires, the deleted sections had no children.
 * The concern is Laratrust role assignments:
 *   - role_user pivot rows with school_section_id = deleted section still exist
 *   - Those users retain the pivot rows but the section no longer appears
 *     in active queries — their scoped role access is effectively suspended
 *   - The section can be restored to reinstate access, or admins can
 *     reassign users to a different section
 *
 * ── Channels ─────────────────────────────────────────────────────────────
 * database: stored in notifications table, shown in app notification bell.
 * mail:     Markdown formatted email. Queued by Laravel's mail channel.
 *
 * ── Payload ──────────────────────────────────────────────────────────────
 * $sections: Collection of soft-deleted SchoolSection instances.
 * deleted_at is set on each model — notification can reference it for context.
 *
 * ── Database Shape ───────────────────────────────────────────────────────
 * type, title, body for notification bell.
 * action_url points to sections index with ?trashed=1 so admin can
 * immediately see and restore deleted sections from the trash view.
 *
 * ── Mail Template ────────────────────────────────────────────────────────
 * Template file:
 *   resources/views/emails/school-section/deleted.blade.php
 *
 * @see App\Listeners\SchoolSection\NotifyAdminOnSectionDeleted
 * @see App\Events\SchoolSection\SchoolSectionDeleted
 */
class SectionDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Collection $sections
    ) {}

    /**
     * Delivery channels.
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
     * Action URL uses ?trashed=1 so admin lands directly on trash view
     * where they can restore the deleted sections in one click.
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

        // Trash view URL — lets admin restore immediately from the email
        $trashUrl = route('settings.sections.index', ['trashed' => 1]);

        return (new MailMessage)
            ->subject("School Section Deleted — {$schoolName}")
            ->markdown('emails.school-section.deleted', [
                'recipientName' => $recipientName,
                'sections'      => $this->sections,
                'count'         => $count,
                'sectionLabel'  => $sectionLabel,
                'schoolName'    => $schoolName,
                'actionUrl'     => $trashUrl,
            ]);
    }

    /**
     * Build the database representation.
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
            'type'          => 'section_deleted',
            'title'         => "School {$sectionLabel} deleted",
            'body'          => $count === 1
                ? "The \"{$names}\" section has been deleted. "
                  . 'Any role assignments scoped to this section are now suspended. '
                  . 'Restore the section to reinstate access, or reassign affected users.'
                : "{$count} sections have been deleted: {$names}. "
                  . 'Role assignments scoped to these sections are now suspended. '
                  . 'Restore the sections to reinstate access, or reassign affected users.',
            'section_names' => $this->sections->pluck('display_name')->toArray(),
            'section_ids'   => $this->sections->pluck('id')->toArray(),
            'count'         => $count,
            // Points to trash view — admin can restore directly
            'action_url'    => route('settings.sections.index', ['trashed' => 1]),
            'action_label'  => 'View Deleted Sections',
        ];
    }
}
