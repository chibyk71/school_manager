<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SchoolSectionResource — Production-Ready
 *
 * Transforms a SchoolSection model instance into a consistent, controlled
 * JSON shape for all frontend consumers.
 *
 * ── Consumers ────────────────────────────────────────────────────────────
 * 1. AdvancedDataTable (index page, axios refetch)
 *    Receives: id, name, display_name, short_code, description, is_active,
 *    source, sort_order, class_levels_count, students_count, created_at,
 *    deleted_at. The DataTable uses source, deleted_at, and counts directly
 *    to drive badge rendering, row styling, and action visibility.
 *
 * 2. Show page (single section detail)
 *    Same fields as above plus classLevels (whenLoaded) for the detail panel.
 *
 * 3. SchoolSectionCollection (wraps this resource for paginated responses)
 *    No extra logic needed — collection wrapping is handled by the
 *    SchoolSectionCollection class.
 *
 * ── What Is Deliberately Excluded ────────────────────────────────────────
 * - school_id: internal tenant key, never needed on frontend
 * - Any password, token, or internal audit field
 * - Laratrust pivot data (role/permission assignments on the team)
 *
 * ── Computed Flags ───────────────────────────────────────────────────────
 * No computed flags (is_trashed, can_delete, is_from_template) are included.
 * The frontend derives these directly from the raw fields:
 *   deleted_at !== null  → is trashed
 *   source === 'template' → is from template
 *   class_levels_count > 0 || students_count > 0 → has children
 * This keeps the Resource as a clean data transformer, not a business
 * logic layer. The frontend's usePermissions() composable handles
 * action visibility independently.
 *
 * ── Date Formatting ──────────────────────────────────────────────────────
 * Dates are formatted to 'Jan 15, 2026' in this Resource rather than
 * returning raw ISO strings. This centralises display formatting on the
 * backend so all frontend consumers get consistent human-readable dates
 * without each component needing to call formatDate().
 * Raw ISO strings are also included (*_raw) for components that need
 * machine-readable dates (e.g. date pickers, relative time calculations).
 *
 * ── Counts ───────────────────────────────────────────────────────────────
 * class_levels_count and students_count come from Eloquent's withCount().
 * They are null-safe — if the controller did not call withCount(), the
 * attribute is absent from the model and whenCounted() returns the
 * fallback value (0) rather than erroring.
 * This means the Resource is safe to use from any controller method
 * regardless of whether withCount() was called.
 *
 * ── Relationships ─────────────────────────────────────────────────────────
 * classLevels is included only when explicitly eager loaded via
 * $section->load('classLevels') or with('classLevels'). whenLoaded()
 * returns null otherwise — the key is omitted from the JSON entirely,
 * keeping the payload lightweight for list views.
 *
 * ── Null Safety ──────────────────────────────────────────────────────────
 * Optional string fields (description) use null coalescing.
 * Date fields use optional chaining on Carbon instances.
 * This prevents the Resource from throwing on partially-loaded models.
 *
 * @see App\Models\SchoolSection
 * @see App\Http\Resources\SchoolSectionCollection
 * @see App\Http\Controllers\Settings\SchoolSectionController
 */
class SchoolSectionResource extends JsonResource
{
    /**
     * Transform the SchoolSection model into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ──────────────────────────────────────────────
            'id'           => $this->id,
            'name'         => $this->name,
            'display_name' => $this->display_name,
            'short_code'   => $this->short_code,
            'description'  => $this->description,

            // ── State ─────────────────────────────────────────────────
            // is_active: frontend uses this for status badge rendering
            // source: 'template' | 'custom' — frontend uses for badge/icon
            // sort_order: used by drag-and-drop reorder on frontend
            'is_active'    => $this->is_active,
            'source'       => $this->source,
            'sort_order'   => $this->sort_order,

            // ── Counts ────────────────────────────────────────────────
            // Safe fallback to 0 when withCount() was not called.
            // Frontend uses these to show child counts in the DataTable
            // and to determine whether delete is safe (counts > 0 → warn).
            'class_levels_count' => $this->class_levels_count ?? 0,
            'students_count'     => $this->students_count ?? 0,

            // ── Dates — formatted for display ─────────────────────────
            // Human-readable format (e.g. "Jan 15, 2026") for display.
            // Raw ISO string also included for components that need it
            // (e.g. relative time tooltips, date-aware filtering).
            'created_at'     => $this->created_at?->format('M j, Y'),
            'created_at_raw' => $this->created_at?->toISOString(),

            // deleted_at is non-null only for soft-deleted records.
            // Frontend uses this to: style trashed rows differently,
            // show "Deleted on Jan 15" in trash view, and determine
            // whether to show Restore vs Delete actions.
            'deleted_at'     => $this->deleted_at?->format('M j, Y'),
            'deleted_at_raw' => $this->deleted_at?->toISOString(),

            // ── Relationships ─────────────────────────────────────────
            // Included only when explicitly eager loaded.
            // Omitted entirely (not null, not []) when not loaded —
            // keeps list responses lean.
            'class_levels' => ClassLevelResource::collection(
                $this->whenLoaded('classLevels')
            ),
        ];
    }
}
