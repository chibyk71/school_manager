<?php

namespace App\Listeners\SchoolSection;

use App\Events\SchoolSection\SchoolSectionDeactivated;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * CascadeDeactivateClassLevels
 *
 * When one or more sections are deactivated, deactivates all class levels
 * that belong to those sections. This maintains data consistency — an
 * active class level under an inactive section creates a confusing state
 * where the class is nominally active but its parent section is not.
 *
 * ── Why A Listener, Not Service Logic ────────────────────────────────────
 * The section deactivation service method (bulkToggleStatus) is responsible
 * for one thing: updating is_active on the section rows. Cascading that
 * deactivation to child class levels is a side effect that belongs in a
 * listener — it keeps the service focused and the cascade opt-in/replaceable
 * without touching service code.
 *
 * ── Direct Eloquent Update ───────────────────────────────────────────────
 * This listener updates ClassLevel directly via Eloquent rather than
 * calling ClassLevelService (which may not exist yet, and creates a
 * circular service dependency). A single whereIn() UPDATE statement
 * handles all affected class levels in one query — efficient even with
 * many sections being deactivated simultaneously.
 *
 * Important: this update does NOT fire ClassLevel model events (updating,
 * updated) because it uses a mass update query. If ClassLevel has its own
 * cascade listeners, use ->each(fn($cl) => $cl->update([...])) instead.
 * For now, direct mass update is intentional and documented here.
 *
 * ── Queued ───────────────────────────────────────────────────────────────
 * Runs in background via ShouldQueue. Deactivating class levels is a
 * side effect that should not block the HTTP response. The section is
 * already deactivated in the DB by the time this job runs — the cascade
 * is eventual consistency, not strict synchronous consistency.
 *
 * ── ShouldHandleEventsAfterCommit ────────────────────────────────────────
 * Ensures this job is only dispatched after the transaction that deactivated
 * the sections commits. Prevents the job running against rolled-back data.
 *
 * ── Failure Handling ─────────────────────────────────────────────────────
 * On unexpected failure, the exception is logged with full section context
 * for production debugging. The job does not retry automatically — an
 * admin can manually re-run deactivation or trigger a maintenance command
 * to sync class level states.
 *
 * @see App\Events\SchoolSection\SchoolSectionDeactivated
 * @see App\Models\ClassLevel (assumed model name — adjust if different)
 */
class CascadeDeactivateClassLevels implements ShouldQueue, ShouldHandleEventsAfterCommit
{
    use InteractsWithQueue;

    /**
     * The queue this job should run on.
     * Uses 'default' — no special priority needed for cascade operations.
     */
    public string $queue = 'default';

    /**
     * Maximum retry attempts before the job is marked as failed.
     * One retry — if the first attempt fails, something is likely wrong
     * with the data or DB state, not a transient network issue.
     */
    public int $tries = 2;

    /**
     * Handle the SchoolSectionDeactivated event.
     *
     * @param  SchoolSectionDeactivated  $event
     * @return void
     */
    public function handle(SchoolSectionDeactivated $event): void
    {
        $sectionIds = $event->sections->pluck('id')->toArray();

        if (empty($sectionIds)) {
            return;
        }

        try {
            // Single mass UPDATE — efficient regardless of how many
            // class levels exist across the deactivated sections.
            // Does not fire ClassLevel model events (intentional — see docblock).
            $affected = \App\Models\Academic\ClassLevel::whereIn('school_section_id', $sectionIds)
                ->where('is_active', true)           // only update currently active ones
                ->update(['is_active' => false]);

            if ($affected > 0) {
                Log::info('CascadeDeactivateClassLevels: class levels deactivated', [
                    'section_ids'           => $sectionIds,
                    'section_names'         => $event->sections->pluck('name')->toArray(),
                    'class_levels_affected' => $affected,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CascadeDeactivateClassLevels: failed to cascade deactivation', [
                'section_ids' => $sectionIds,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            // Re-throw so the job is marked as failed in the jobs table
            // and appears in failed_jobs for manual inspection/retry.
            throw $e;
        }
    }
}
