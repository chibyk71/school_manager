<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TimetableResource — API / Inertia Response Formatter for Timetable Headers
 *
 * ── What This Does ────────────────────────────────────────────────────────────────
 * Transforms a Timetable Eloquent model into a clean, consistent JSON/array shape
 * for use in:
 *   - Inertia page props (TimetableIndex.vue, TimetableBuilder.vue)
 *   - JSON API responses (mobile, external integrations)
 *   - DataTable rows in the timetable listing page
 *
 * ── Design Decisions ─────────────────────────────────────────────────────────────
 * 1. **Flat shape preferred over deeply nested**: The frontend DataTable and list
 *    view need scalar values for sorting/filtering. Relations are included as flat
 *    name strings rather than nested objects where possible.
 *
 * 2. **Counts over full collections**: This resource represents the timetable
 *    HEADER. Slot details are provided by TimetableSlotResource on the builder
 *    endpoint. Including all slots here would massively inflate index responses.
 *
 * 3. **Status helpers included**: `is_draft`, `is_active`, `is_archived` as booleans
 *    alongside the raw `status` string. Lets the frontend avoid string comparisons.
 *
 * 4. **`generated_at` and `generated_by` explicit**: The frontend needs to know
 *    whether generation has ever been run (generated_at null = never generated).
 *    The generator user's name is included for the audit trail display.
 *
 * 5. **Conflict summary included**: `conflict_count` and `unresolved_conflict_count`
 *    drive the conflict badge on the list view and the activation button guard.
 *    Loading the full conflicts collection here is a deliberate tradeoff — we use
 *    `withCount` in the controller so these are zero-query attributes.
 *
 * ── Required Eager Loads (controller responsibility) ─────────────────────────────
 * For correct output, the controller must eager-load:
 *   - `schoolSection:id,name,display_name`
 *   - `term:id,name`
 *   - `generatedBy:id,name` (nullable)
 *   - `daySchedules.periodSchedule:id,name`
 *   - withCount(['slots', 'unresolvedConflicts'])   (as slot_count, unresolved_conflict_count)
 *
 * Missing relations are handled gracefully (null / 0 fallback) so the resource
 * never throws even with partial loading.
 *
 * ── Output Shape ─────────────────────────────────────────────────────────────────
 * {
 *   "id":                      "uuid",
 *   "title":                   "2025/2026 First Term — JSS",
 *   "status":                  "draft",
 *   "is_draft":                true,
 *   "is_active":               false,
 *   "is_archived":             false,
 *   "section_id":              "uuid",
 *   "section_name":            "Junior Secondary School",
 *   "term_id":                 "uuid",
 *   "term_name":               "First Term",
 *   "effective_from":          "2025-09-01",
 *   "effective_to":            null,
 *   "slot_count":              42,
 *   "unresolved_conflict_count": 3,
 *   "has_conflicts":           true,
 *   "can_activate":            false,
 *   "generated_at":            "2025-09-01 10:30:00",
 *   "generated_by_name":       "John Admin",
 *   "notes":                   null,
 *   "options":                 {},
 *   "working_days":            [1, 2, 3, 4, 5],
 *   "day_schedules":           [ { day_of_week, day_name, schedule_id, schedule_name } ],
 *   "created_at":              "2025-08-30 09:00:00",
 *   "updated_at":              "2025-09-01 10:30:00"
 * }
 */
class TimetableResource extends JsonResource
{
    /**
     * Day-of-week integer to label mapping (ISO 8601).
     */
    private const DAY_NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unresolvedCount = $this->unresolved_conflicts_count
            ?? $this->unresolvedConflicts()->count();

        $slotCount = $this->slots_count
            ?? $this->slots()->count();

        return [
            // ── Identity ───────────────────────────────────────────────────────
            'id'    => $this->id,
            'title' => $this->title,

            // ── Status ─────────────────────────────────────────────────────────
            'status'      => $this->status,
            'is_draft'    => $this->isDraft(),
            'is_active'   => $this->isActive(),
            'is_archived' => $this->isArchived(),

            // ── Section ────────────────────────────────────────────────────────
            'section_id'   => $this->school_section_id,
            'section_name' => $this->whenLoaded('schoolSection', function () {
                return $this->schoolSection->display_name
                    ?? $this->schoolSection->name
                    ?? null;
            }),

            // ── Term ───────────────────────────────────────────────────────────
            'term_id'   => $this->term_id,
            'term_name' => $this->whenLoaded('term', fn () => $this->term?->name),

            // ── Date range ─────────────────────────────────────────────────────
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to'   => $this->effective_to?->toDateString(),

            // ── Counts & conflict summary ───────────────────────────────────────
            'slot_count'                => $slotCount,
            'unresolved_conflict_count' => $unresolvedCount,
            'has_conflicts'             => $unresolvedCount > 0,
            // The activation button is disabled when conflicts exist OR status != draft
            'can_activate'              => $this->isDraft() && $unresolvedCount === 0,

            // ── Generation metadata ────────────────────────────────────────────
            'generated_at' => $this->generated_at?->toDateTimeString(),
            'generated_by_name' => $this->whenLoaded(
                'generatedBy',
                fn () => $this->generatedBy?->name
            ),

            // ── Notes & options ────────────────────────────────────────────────
            'notes'   => $this->notes,
            'options' => $this->options ?? [],

            // ── Day schedule mappings ──────────────────────────────────────────
            // Compact array: one entry per working day. The frontend uses this to
            // render the correct period columns for each day column in the grid.
            'working_days'  => $this->workingDays(),
            'day_schedules' => $this->whenLoaded('daySchedules', function () {
                return $this->daySchedules
                    ->sortBy('day_of_week')
                    ->map(fn ($ds) => [
                        'day_of_week'   => $ds->day_of_week,
                        'day_name'      => self::DAY_NAMES[$ds->day_of_week] ?? "Day {$ds->day_of_week}",
                        'schedule_id'   => $ds->period_schedule_id,
                        'schedule_name' => $ds->periodSchedule?->name ?? null,
                    ])
                    ->values();
            }),

            // ── Timestamps ────────────────────────────────────────────────────
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
