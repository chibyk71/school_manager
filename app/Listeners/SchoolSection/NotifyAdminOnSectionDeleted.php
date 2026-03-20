<?php

namespace App\Listeners\SchoolSection;

use App\Events\SchoolSection\SchoolSectionDeleted;
use App\Models\User;
use App\Notifications\SchoolSection\SectionDeletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyAdminOnSectionDeleted
 *
 * Notifies school administrators when one or more sections are soft-deleted.
 *
 * ── Why Notify On Delete ─────────────────────────────────────────────────
 * When a section is deleted, Laratrust role assignments scoped to that
 * section (role_user rows with school_section_id = deleted section) become
 * effectively inactive — users still have the pivot rows but the section
 * no longer appears in active queries. Admins need to know so they can
 * decide whether to reassign roles or restore the section.
 *
 * Note: the service layer blocks deletion when class levels or enrolled
 * students exist. By the time this listener fires, the deleted sections
 * had no children — the orphaned concern is role assignments only.
 *
 * ── Who Gets Notified ────────────────────────────────────────────────────
 * Users who have the 'sections.view-any' permission in the current school.
 * This targets school administrators without hardcoding a role name —
 * which roles carry this permission is a per-tenant concern.
 *
 * The query uses withoutGlobalScope to bypass BelongsToSchool scope
 * because listeners run outside a normal request context and GetSchoolModel()
 * may not be available in a queued job. school_id is taken directly from
 * the first deleted section's school_id instead.
 *
 * ── Notification Channels ────────────────────────────────────────────────
 * SectionDeletedNotification delivers via:
 *   - database: shows in the app notification bell
 *   - mail: sends email to admin (queued separately by Laravel's mailer)
 *
 * ── Queued ───────────────────────────────────────────────────────────────
 * Runs in background. Notification sending must never block the HTTP
 * response that confirmed the deletion to the user.
 *
 * ── ShouldHandleEventsAfterCommit ────────────────────────────────────────
 * Only dispatched after the soft-delete transaction commits. Prevents
 * notifying admins about a deletion that was later rolled back.
 *
 * @see App\Events\SchoolSection\SchoolSectionDeleted
 * @see App\Notifications\SchoolSection\SectionDeletedNotification
 */
class NotifyAdminOnSectionDeleted implements ShouldQueue, \Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    /**
     * Handle the SchoolSectionDeleted event.
     *
     * @param  SchoolSectionDeleted  $event
     * @return void
     */
    public function handle(SchoolSectionDeleted $event): void
    {
        $sections = $event->sections;

        if ($sections->isEmpty()) {
            return;
        }

        // Resolve school_id from the deleted sections directly.
        // Cannot use GetSchoolModel() in a queued job — no request context.
        $schoolId = $sections->first()->school_id;

        if (!$schoolId) {
            Log::warning('NotifyAdminOnSectionDeleted: could not resolve school_id', [
                'section_ids' => $sections->pluck('id')->toArray(),
            ]);
            return;
        }

        try {
            // Fetch admins: users with sections.view-any permission in this school.
            // withoutGlobalScope bypasses BelongsToSchool — we target by school_id directly.
            $admins = User::withoutGlobalScope(\App\Models\Scopes\SchoolScope::class)
                ->whereHas('schools', fn($q) => $q->where('schools.id', $schoolId))
                ->get()
                ->filter(fn(User $user) => $user->hasPermission('sections.view-any'));

            if ($admins->isEmpty()) {
                Log::info('NotifyAdminOnSectionDeleted: no admins found to notify', [
                    'school_id' => $schoolId,
                    'section_ids' => $sections->pluck('id')->toArray(),
                ]);
                return;
            }

            // Send notification to each admin.
            // Laravel's notification system queues the mail channel internally.
            $notification = new SectionDeletedNotification($sections);

            foreach ($admins as $admin) {
                $admin->notify($notification);
            }

            Log::info('NotifyAdminOnSectionDeleted: admins notified', [
                'school_id' => $schoolId,
                'section_names' => $sections->pluck('name')->toArray(),
                'notified_count' => $admins->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('NotifyAdminOnSectionDeleted: failed to send notifications', [
                'school_id' => $schoolId,
                'section_ids' => $sections->pluck('id')->toArray(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
