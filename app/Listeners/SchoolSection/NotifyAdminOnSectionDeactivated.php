<?php

namespace App\Listeners\SchoolSection;

use App\Events\SchoolSection\SchoolSectionDeactivated;
use App\Models\User;
use App\Notifications\SchoolSection\SectionDeactivatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * NotifyAdminOnSectionDeactivated
 *
 * Notifies school administrators when one or more sections are deactivated,
 * informing them of cascade effects on class levels and suspended role access.
 *
 * ── Pattern ──────────────────────────────────────────────────────────────
 * Identical pattern to NotifyAdminOnSectionDeleted — same admin resolution
 * strategy, same queuing approach, same error handling. The only differences
 * are the event type and the notification class used.
 *
 * ── Admin Resolution ─────────────────────────────────────────────────────
 * Resolves school_id from the event payload directly (not GetSchoolModel())
 * because this runs as a queued job outside the HTTP request lifecycle.
 * Targets users with 'sections.view-any' permission — permission-based
 * targeting rather than hardcoded role names for multi-tenant flexibility.
 *
 * ── Queued ───────────────────────────────────────────────────────────────
 * Runs in background. Notification sending must never block the response
 * that confirmed the deactivation to the user.
 *
 * ── ShouldHandleEventsAfterCommit ────────────────────────────────────────
 * Only dispatched after the deactivation transaction commits, ensuring
 * admins are not notified about a deactivation that was rolled back.
 *
 * @see App\Events\SchoolSection\SchoolSectionDeactivated
 * @see App\Notifications\SchoolSection\SectionDeactivatedNotification
 */
class NotifyAdminOnSectionDeactivated implements ShouldQueue, \Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    /**
     * Handle the SchoolSectionDeactivated event.
     *
     * @param  SchoolSectionDeactivated  $event
     * @return void
     */
    public function handle(SchoolSectionDeactivated $event): void
    {
        $sections = $event->sections;

        if ($sections->isEmpty()) {
            return;
        }

        // Resolve school_id from event payload — GetSchoolModel() unavailable in queued jobs
        $schoolId = $sections->first()->school_id;

        if (! $schoolId) {
            Log::warning('NotifyAdminOnSectionDeactivated: could not resolve school_id', [
                'section_ids' => $sections->pluck('id')->toArray(),
            ]);
            return;
        }

        try {
            // Fetch admins: users with sections.view-any permission in this school.
            // withoutGlobalScope bypasses BelongsToSchool — target by school_id directly.
            $admins = User::withoutGlobalScope(\App\Models\Scopes\SchoolScope::class)
                ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('sections.view-any'));

            if ($admins->isEmpty()) {
                Log::info('NotifyAdminOnSectionDeactivated: no admins found to notify', [
                    'school_id'   => $schoolId,
                    'section_ids' => $sections->pluck('id')->toArray(),
                ]);
                return;
            }

            $notification = new SectionDeactivatedNotification($sections);

            foreach ($admins as $admin) {
                $admin->notify($notification);
            }

            Log::info('NotifyAdminOnSectionDeactivated: admins notified', [
                'school_id'      => $schoolId,
                'section_names'  => $sections->pluck('name')->toArray(),
                'notified_count' => $admins->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('NotifyAdminOnSectionDeactivated: failed to send notifications', [
                'school_id'   => $schoolId,
                'section_ids' => $sections->pluck('id')->toArray(),
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
