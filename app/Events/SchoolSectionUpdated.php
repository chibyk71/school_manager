<?php

namespace App\Events\SchoolSection;

use App\Models\SchoolSection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SchoolSectionUpdated
 *
 * Fired by SchoolSectionService after a SchoolSection is successfully updated
 * via update(). This event covers general field changes (name, display_name,
 * short_code, description, sort_order).
 *
 * ── Not Fired For ────────────────────────────────────────────────────────
 * - is_active changes: SchoolSectionDeactivated is fired instead when a
 *   section is deactivated, because deactivation has cascade side effects
 *   (suspending enrollments, notifying admins) that general updates do not.
 * - sort_order changes via reorder(): reorder() does not fire this event
 *   to avoid triggering cache invalidation N times for a bulk positional
 *   update — SectionCache::forget() is called directly in the service.
 *
 * ── Payload ──────────────────────────────────────────────────────────────
 * $section: the updated SchoolSection instance after refresh().
 * Listeners receive the post-update state of the model.
 * If listeners need the previous values (e.g. for audit logging), they
 * should use $section->getChanges() and $section->getOriginal() before
 * the model state is serialized by the queue.
 *
 * ── Expected Listeners ───────────────────────────────────────────────────
 * - InvalidateSectionCache → clears per-school section cache
 *
 * @see App\Services\SchoolSectionService::update()
 */
class SchoolSectionUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SchoolSection $section
    ) {}
}
