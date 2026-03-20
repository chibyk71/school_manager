<?php

namespace App\Events\SchoolSection;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SchoolSectionDeleted
 *
 * Fired by SchoolSectionService after one or more sections are successfully
 * soft-deleted via bulkDelete().
 *
 * ── Payload ──────────────────────────────────────────────────────────────
 * $sections: Collection of soft-deleted SchoolSection instances.
 *   Always a Collection — the service operates on arrays and the
 *   useDeleteResource composable always sends an array of IDs.
 *   Listeners receive the model instances in their post-delete state
 *   (deleted_at is set, they are no longer returned by default queries).
 *
 * ── Not Fired For Force Delete ───────────────────────────────────────────
 * bulkForceDelete() does not fire this event. Force-deleted records are
 * permanently removed and cannot be referenced by listeners after the fact.
 * SectionCache::forget() is called directly in the service after force delete.
 *
 * ── Expected Listeners ───────────────────────────────────────────────────
 * - InvalidateSectionCache      → clears per-school section cache
 * - NotifyAdminOnSectionDeleted → alerts school admin (sections with children
 *                                 are blocked at service layer, so any deletion
 *                                 that reaches this event is safe)
 *
 * @see App\Services\SchoolSectionService::bulkDelete()
 */
class SchoolSectionDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Collection $sections
    ) {}
}
