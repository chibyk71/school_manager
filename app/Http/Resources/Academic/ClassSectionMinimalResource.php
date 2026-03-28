<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ClassSectionMinimalResource — lightweight serialization for dropdowns and selects.
 *
 * ── Purpose ───────────────────────────────────────────────────────────────────
 * A deliberately small resource used wherever a full ClassSectionResource
 * would be wasteful — typically dropdown/select option lists used by:
 *   - Student enrollment form (pick which section to enroll into)
 *   - Timetable builder (select section for a slot)
 *   - Result entry (filter by section)
 *   - Teacher portal (show assigned sections)
 *   - Any AsyncSelect component using the /options endpoint
 *
 * ── What It Includes ──────────────────────────────────────────────────────────
 * Only the fields needed to render and identify an option in a dropdown:
 *   id           — the value submitted with forms
 *   name         — the arm label ("A", "Diamond")
 *   display_name — the full label shown in UI ("JSS 1A", "Primary 2 Diamond")
 *   label        — alias for display_name formatted for PrimeVue Select
 *   value        — alias for id (PrimeVue Select option-value binding)
 *   status       — so frontend can show/disable inactive sections
 *   capacity     — so enrollment forms can show remaining slots
 *   is_at_capacity — quick flag for disabling full sections in dropdowns
 *   students_count — only if pre-loaded; optional enrollment indicator
 *
 * ── PrimeVue AsyncSelect Compatibility ───────────────────────────────────────
 * The `label` and `value` fields are included so this resource works directly
 * with your existing AsyncSelect.vue component which expects:
 *   { label: string, value: string }
 * No transformation needed on the frontend.
 */
class ClassSectionMinimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'display_name'   => $this->display_name_computed,
            'class_level_id' => $this->class_level_id,

            // Optional parent context — included when classLevel is eager-loaded
            // (useful for grouping options in a GroupBy select)
            'class_level_name' => $this->whenLoaded(
                'classLevel',
                fn () => $this->classLevel->name
            ),

            // PrimeVue Select compatibility
            'label' => $this->display_name_computed,
            'value' => $this->id,

            // Enrollment state indicators for smart dropdowns
            'status'         => $this->status,
            'is_active'      => $this->status === 'active',
            'capacity'       => $this->capacity,
            'is_uncapped'    => $this->capacity === 0,
            'students_count' => $this->whenCounted('students'),
            'is_at_capacity' => $this->is_at_capacity,
        ];
    }
}
