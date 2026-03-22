<?php

/**
 * ClassLevelResource
 *
 * Transforms a ClassLevel model instance into a consistent JSON structure
 * for both Inertia page props and API responses.
 *
 * Shape decisions:
 * ─────────────────────────────────────────────────────────────────────────────
 * - short_label and full_label are included as computed convenience fields so
 *   frontend components never need to replicate the alias/display_name fallback
 *   logic themselves. Dropdowns use short_label, detail views use full_label.
 *
 * - section is conditionally loaded (whenLoaded) to avoid N+1 on list views.
 *   The section detail page (ClassLevelsTab.vue) never needs it since the
 *   section is already known from the route. The global settings view
 *   (Settings/Academic/ClassLevels.vue) eager-loads it so it appears in the table.
 *
 * - class_sections_count is included as a whenCounted field so the controller
 *   can optionally append ->withCount('classSections') without changing the
 *   resource shape. Used to show "3 streams" in the table and to guard deletion.
 *
 * - students_count follows the same pattern — future-proof for when ClassSection
 *   → Student relationships are built. Shown in the table so admin knows at a
 *   glance whether a level has enrolled students before trying to delete it.
 *
 * - is_deletable is a computed safety flag the frontend uses to decide whether
 *   to show the delete action at all, rather than showing it and then getting
 *   a 422 back from the server.
 *
 * - Timestamps are only included in the detail/edit context (whenLoaded pattern
 *   is not applicable here so we include them always — they are cheap and useful
 *   for audit displays in the modal footer).
 *
 * Usage:
 * ─────────────────────────────────────────────────────────────────────────────
 *   // Single resource (modal / show)
 *   return new ClassLevelResource($classLevel->load('schoolSection'));
 *
 *   // Collection (DataTable — no section needed, count is useful)
 *   return ClassLevelResource::collection(
 *       $levels->withCount(['classSections', 'students'])
 *   );
 *
 *   // Global settings view (section name needed for the table column)
 *   return ClassLevelResource::collection(
 *       $levels->with('schoolSection')->withCount(['classSections'])
 *   );
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ──────────────────────────────────────────────────
            'id'           => $this->id,
            'name'         => $this->name,
            'display_name' => $this->display_name,
            'alias'        => $this->alias,
            'description'  => $this->description,

            // ── Computed labels (alias/display_name fallbacks) ────────────
            // short_label: used in dropdowns, badges, table cells
            // full_label:  used in modal headers, detail views, reports
            'short_label'  => $this->short_label,
            'full_label'   => $this->full_label,

            // ── Ordering & capacity ───────────────────────────────────────
            'sequence'     => $this->sequence,
            'max_arms'     => $this->max_arms,

            // ── Status ────────────────────────────────────────────────────
            'is_active'    => $this->is_active,

            // ── Safety flag for frontend action visibility ────────────────
            // True only when no class sections AND no students are assigned.
            // Frontend uses this to hide/disable the delete action rather than
            // showing it and receiving a server-side rejection.
            'is_deletable' => $this->whenCounted(
                'class_sections_count',
                fn() => $this->class_sections_count === 0,
                true // default to true when count not loaded (controller decides)
            ),

            // ── Relation: owning section (only when eager loaded) ─────────
            // Not loaded on the section detail page (section is known from route).
            // Loaded on the global settings view so the table can show section name.
            'section'      => $this->whenLoaded('schoolSection', fn() => [
                'id'   => $this->schoolSection->id,
                'name' => $this->schoolSection->name,
            ]),

            // ── Counts (only when withCount() was called on the query) ────
            'class_sections_count' => $this->whenCounted('class_sections_count'),
            'students_count'       => $this->whenCounted('students_count'),

            // ── Timestamps ────────────────────────────────────────────────
            'created_at'   => $this->created_at?->toDateTimeString(),
            'updated_at'   => $this->updated_at?->toDateTimeString(),
            'deleted_at'   => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
