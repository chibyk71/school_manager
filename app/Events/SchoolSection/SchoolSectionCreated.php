<?php

namespace App\Events\SchoolSection;

use App\Models\SchoolSection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SchoolSectionCreated
 *
 * Fired by SchoolSectionService after a single SchoolSection is successfully
 * created — either via createOne() (manual form) or per-record inside
 * createFromTemplates() (template bulk creation fires one event per section).
 *
 * ── Payload ──────────────────────────────────────────────────────────────
 * $section: the freshly created SchoolSection instance, fully persisted.
 * Listeners receive the complete model with all attributes including the
 * auto-assigned school_id (from BelongsToSchool boot hook) and sort_order
 */
class SchoolSectionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SchoolSection $section,
    ) {}
}
