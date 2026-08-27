<?php

namespace App\Events\SchoolSection;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SchoolSectionRestored
 *
 * Fired by SchoolSectionService after one or more soft-deleted sections
 * are successfully restored via bulkRestore().
 *
 * ── Payload ──────────────────────────────────────────────────────────────
 * $sections: Collection of restored SchoolSection instances.
 *   Always a Collection — the service operates on arrays and the
 *   useRestoreResource composable always sends an array of IDs.
 *   Listeners receive the model instances in their post-restore state
 *   (deleted_at is null, they are visible in default queries again).
 *
 * ── Expected Listeners ───────────────────────────────────────────────────
 * - InvalidateSectionCache       → clears per-school section cache
 * - SyncLaratrustTeamOnRestore   → re-registers the section as a Laratrust
 *                                  Team if it was removed during soft-delete
 *                                  (depends on whether observer cleans up
 *                                  team records on delete — add this listener
 *                                  if that cleanup is implemented)
 *
 * @see App\Services\SchoolSectionService::bulkRestore()
 */
class SchoolSectionRestored
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Collection $sections
    ) {}
}
