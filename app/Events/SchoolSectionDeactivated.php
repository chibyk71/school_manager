<?php

namespace App\Events\SchoolSection;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SchoolSectionDeactivated
 *
 * Fired by SchoolSectionService when one or more sections are deactivated
 * (is_active set to false) via bulkToggleStatus().
 *
 * ── Why Separate From SchoolSectionUpdated ───────────────────────────────
 * Deactivation has cascade side effects that general updates do not:
 *   - Class levels under the section may need to be suspended
 *   - Students currently enrolled may need to be notified
 *   - Admin notification about affected enrollments
 *   - Potential blocking of new enrollment assignments to this section
 *
 * Keeping this as a dedicated event lets listeners opt in specifically to
 * deactivation without being triggered by every field update.
 *
 * ── Not Fired For Activation ─────────────────────────────────────────────
 * When sections are activated (is_active = true), no event is fired.
 * Activation is a routine operation with no cascade side effects.
 * The service calls SectionCache::forget() directly after activation.
 *
 * ── Payload ──────────────────────────────────────────────────────────────
 * $sections: Collection of deactivated SchoolSection instances.
 *   Always a Collection even when a single section was deactivated,
 *   because bulkToggleStatus() always operates on arrays.
 *
 * ── Expected Listeners ───────────────────────────────────────────────────
 * - InvalidateSectionCache        → clears per-school section cache
 * - CascadeDeactivateClassLevels  → marks child class levels as inactive
 * - NotifyAdminOnSectionDeactivated → alerts school admin of affected sections
 *
 * @see App\Services\SchoolSectionService::bulkToggleStatus()
 */
class SchoolSectionDeactivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Collection $sections
    ) {}
}
