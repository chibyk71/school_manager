<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ClassSectionResource — JSON serialization for ClassSection.
 *
 * ── Purpose ───────────────────────────────────────────────────────────────────
 * Transforms a ClassSection model (with optional eager-loaded relations) into
 * a consistent, frontend-ready JSON shape used by:
 *   - The AdvancedDataTable (index listing)
 *   - The section detail/edit modal
 *   - Dropdown option lists (via ClassSectionMinimalResource)
 *   - Inertia page props
 *
 * ── Design Principles ─────────────────────────────────────────────────────────
 * 1. Never expose raw UUIDs for related models — surface the display data
 *    directly so the frontend never needs to join or look up separately.
 *
 * 2. Counts are only included when pre-loaded via withCount().
 *    If not loaded they are omitted (null-safe) rather than triggering N+1.
 *    The DataTable always calls withCount(['students', 'teacherSubjectAssignments'])
 *    before passing data to this resource.
 *
 * 3. Dates are provided in two formats:
 *    - Human-readable string for display ("Jan 15, 2026")
 *    - Raw ISO string for date pickers and relative-time computation
 *    This pattern is consistent with SchoolSectionResource and ClassLevelResource.
 *
 * 4. Relation data is only included when the relation is loaded.
 *    whenLoaded() prevents N+1 queries on list responses.
 *
 * ── Conditional Loading ───────────────────────────────────────────────────────
 * Index (list) response: classLevel, formTeacher counts — always eager-loaded
 * Detail response: adds teacherSubjectAssignments with nested teacher+subject
 *
 * ── Frontend Type Alignment ───────────────────────────────────────────────────
 * This resource must stay in sync with the TypeScript ClassSection interface
 * defined in resources/js/types/class-section.ts.
 */
class ClassSectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ──────────────────────────────────────────────────
            'id' => $this->id,

            // ── Naming ────────────────────────────────────────────────────
            // The arm label stored in the DB ("A", "B", "Diamond")
            'name' => $this->name,

            // The full display label ("JSS 1A", "Primary 2 Diamond").
            // Uses the accessor which falls back to computed value when null.
            'display_name' => $this->display_name_computed,

            // The raw stored display_name (null if not yet set / using computed).
            // Frontend needs this to distinguish admin-customised vs auto names.
            'display_name_stored' => $this->display_name,

            // ── Parent class level ─────────────────────────────────────────
            // Always include the raw FK for filter/group-by operations
            'class_level_id' => $this->class_level_id,

            // Include relation data when eager-loaded (always on index/detail)
            'class_level' => $this->whenLoaded('classLevel', fn() => [
                'id' => $this->classLevel->id,
                'name' => $this->classLevel->name,
                'display_name' => $this->classLevel->display_name,
                // Include the school section name for breadcrumbs/filters
                'school_section' => $this->classLevel->whenLoaded(
                    'schoolSection',
                    fn() => [
                        'id' => $this->classLevel->schoolSection->id,
                        'name' => $this->classLevel->schoolSection->name,
                        'display_name' => $this->classLevel->schoolSection->display_name,
                    ]
                ),
            ]),

            // ── Physical room ─────────────────────────────────────────────
            'room' => $this->room,

            // ── Capacity & enrollment ─────────────────────────────────────
            'capacity' => $this->capacity,

            // 0 means uncapped — frontend uses this to show "Uncapped" vs "40 max"
            'is_uncapped' => $this->capacity === 0,

            // Current enrollment count (only if pre-loaded via withCount)
            'students_count' => $this->whenCounted('students'),

            // Whether the section has reached capacity (uses the model accessor)
            'is_at_capacity' => $this->is_at_capacity,

            // Remaining slots — null if uncapped, integer otherwise
            'remaining_capacity' => $this->getRemainingCapacity(),

            // ── Form teacher ──────────────────────────────────────────────
            'form_teacher_id' => $this->form_teacher_id,

            'form_teacher' => $this->whenLoaded('formTeacher', fn() => $this->formTeacher ? [
                'id' => $this->formTeacher->id,
                // Assumes Staff model has a full_name accessor or profile relation
                // Adjust to match your Staff model's actual name field
                'full_name' => $this->formTeacher->full_name
                    ?? ($this->formTeacher->profile?->full_name)
                    ?? 'Unknown',
                'staff_id_number' => $this->formTeacher->staff_id_number ?? null,
            ] : null),

            // ── Subject assignments ────────────────────────────────────────
            // Only included on detail responses (not list/DataTable)
            'teacher_subject_assignments' => $this->whenLoaded(
                'teacherSubjectAssignments',
                fn() => $this->teacherSubjectAssignments->map(fn($assignment) => [
                    'id' => $assignment->id,
                    'teacher_id' => $assignment->teacher_id,
                    'subject_id' => $assignment->subject_id,
                    'role' => $assignment->role,
                    'role_label' => $assignment->getEffectiveRoleLabel(),
                    'teacher' => $assignment->relationLoaded('teacher') ? [
                        'id' => $assignment->teacher->id,
                        'full_name' => $assignment->teacher->full_name
                            ?? ($assignment->teacher->profile?->full_name)
                            ?? 'Unknown',
                    ] : null,
                    'subject' => $assignment->relationLoaded('subject') ? [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name,
                        'code' => $assignment->subject->code ?? null,
                    ] : null,
                ])
            ),

            // Count of subject assignments (only if pre-loaded via withCount)
            'teacher_subject_assignments_count' => $this->whenCounted(
                'teacherSubjectAssignments'
            ),

            // ── Display order ─────────────────────────────────────────────
            'sort_order' => $this->sort_order,

            // ── Status ────────────────────────────────────────────────────
            'status' => $this->status,
            'is_active' => $this->status === 'active',

            // ── Soft delete ───────────────────────────────────────────────
            // Non-null deleted_at = section is in the trash
            'deleted_at' => $this->deleted_at?->toISOString(),
            'deleted_at_for_humans' => $this->deleted_at?->diffForHumans(),
            'is_trashed' => $this->trashed(),

            // ── Timestamps ────────────────────────────────────────────────
            // Formatted for display in the DataTable
            'created_at' => $this->created_at?->format('M j, Y'),
            'created_at_iso' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->format('M j, Y'),
            'updated_at_iso' => $this->updated_at?->toISOString(),
        ];
    }
}
