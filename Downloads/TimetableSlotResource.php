<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TimetableSlotResource — API / Inertia Response Formatter for Timetable Grid Slots
 *
 * ── What This Does ────────────────────────────────────────────────────────────────
 * Transforms a TimetableSlot Eloquent model into the flat, frontend-ready shape
 * needed to render the weekly timetable grid. Used by:
 *   - TimetableBuilder.vue (admin edit view — full detail)
 *   - TimetableView.vue (read-only student/teacher view)
 *   - Any API client consuming the timetable grid endpoint
 *
 * ── Design Decisions ─────────────────────────────────────────────────────────────
 * 1. **Flat shape, not nested**: The grid cell component (TimetableSlotCell.vue)
 *    needs direct access to teacher name, subject name, period name, and section
 *    name without deep object traversal. All are surfaced at the root level.
 *
 * 2. **Grid positioning coordinates included**: `day_of_week` and `period_order`
 *    are the two coordinates that place a slot in the correct grid cell. The
 *    frontend builds a keyed lookup `{ [day:period]: slot }` from these.
 *
 * 3. **Period timing included**: `period_start_time` and `period_end_time` are
 *    computed by PeriodSchedule::computedPeriodTimes() and stored nowhere — they
 *    must be passed down. We include them here so the cell can show "09:00–09:45".
 *    These will be null if the period schedule start time is not configured.
 *
 * 4. **Color for visual differentiation**: `subject_color` (or a derived color
 *    from the subject ID) lets the grid colour-code cells by subject so teachers
 *    and students can glance-read the timetable.
 *
 * 5. **Drag-drop safety flags included**: `is_manually_placed` drives the lock
 *    icon on the cell. The `can_move` flag combines is_manually_placed with the
 *    timetable status (only draft timetables allow moves).
 *
 * 6. **Minimal teacher data**: We include teacher name, abbreviated to first name
 *    + last name initial (e.g. "Mrs. Adaeze O.") for compact cell display, plus
 *    the full name for the tooltip/detail panel.
 *
 * ── Required Eager Loads (controller responsibility) ─────────────────────────────
 * For correct output, the controller must eager-load:
 *   - `classSection:id,name,display_name`
 *   - `period:id,name,order,duration_minutes,is_break`
 *   - `assignment.subject:id,name,color`
 *   - `assignment.teacher.profile:id,first_name,last_name,title`  (or profile accessor)
 *   - `timetable:id,status`  (for can_move flag)
 *
 * Period timing is conditionally included when the `period` is loaded AND the
 * period schedule's computed times are passed via the `$this->resource->start_time`
 * virtual attribute (set by the controller before returning the resource collection).
 *
 * ── Output Shape ─────────────────────────────────────────────────────────────────
 * {
 *   "id":                   "uuid",
 *   "timetable_id":         "uuid",
 *   "day_of_week":          2,
 *   "day_name":             "Tuesday",
 *   "class_section_id":     "uuid",
 *   "class_section_name":   "JSS 1A",
 *   "period_id":            5,
 *   "period_name":          "Period 3",
 *   "period_order":         3,
 *   "period_start_time":    "10:45",
 *   "period_end_time":      "11:30",
 *   "period_duration_min":  45,
 *   "subject_id":           "uuid",
 *   "subject_name":         "Mathematics",
 *   "subject_color":        "#6366f1",
 *   "teacher_id":           5,
 *   "teacher_name":         "Mrs. Adaeze O.",
 *   "teacher_full_name":    "Mrs. Adaeze Okonkwo",
 *   "tcss_id":              "uuid",
 *   "is_manually_placed":   true,
 *   "is_break":             false,
 *   "can_move":             true,
 *   "notes":                null,
 *   "created_at":           "2025-09-01 10:30:00"
 * }
 */
class TimetableSlotResource extends JsonResource
{
    /**
     * Day-of-week integer to label mapping (ISO 8601, 1=Monday).
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
     * A map of computed period start/end times keyed by period_id.
     * Set on the resource by the controller before returning the collection:
     *
     *   $resource->additional(['periodTimes' => $schedule->computedPeriodTimes()]);
     *
     * Shape: [ period_id => ['start' => '09:00', 'end' => '09:45'], ... ]
     *
     * @var array<int, array{start: string, end: string}>
     */
    public array $periodTimes = [];

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // ── Resolve timing for this slot's period ─────────────────────────────────
        $periodTiming = $this->periodTimes[$this->class_period_id] ?? null;

        // ── Resolve teacher display name ──────────────────────────────────────────
        [$teacherShort, $teacherFull, $teacherId] = $this->resolveTeacherNames();

        // ── Subject color (use explicit color or derive from ID) ──────────────────
        $subjectColor = $this->whenLoaded('assignment', function () {
            $color = $this->assignment->subject?->color ?? null;
            if ($color) {
                return $color;
            }
            // Deterministic color derived from subject ID when no explicit color set
            return $this->deriveColorFromId($this->assignment->subject_id ?? '');
        });

        // ── Can the slot be moved? Only draft timetables allow it ─────────────────
        $timetableIsDraft = $this->whenLoaded(
            'timetable',
            fn () => $this->timetable?->isDraft() ?? false,
            false
        );

        return [
            // ── Identity ───────────────────────────────────────────────────────────
            'id'           => $this->id,
            'timetable_id' => $this->timetable_id,

            // ── Grid coordinates ───────────────────────────────────────────────────
            'day_of_week' => $this->day_of_week,
            'day_name'    => self::DAY_NAMES[$this->day_of_week] ?? "Day {$this->day_of_week}",

            // ── Class section ─────────────────────────────────────────────────────
            'class_section_id'   => $this->class_section_id,
            'class_section_name' => $this->whenLoaded('classSection', function () {
                return $this->classSection->display_name
                    ?? $this->classSection->name
                    ?? null;
            }),

            // ── Period ────────────────────────────────────────────────────────────
            'period_id'           => $this->class_period_id,
            'period_name'         => $this->whenLoaded('period', fn () => $this->period?->name),
            'period_order'        => $this->whenLoaded('period', fn () => $this->period?->order),
            'period_start_time'   => $periodTiming['start'] ?? null,
            'period_end_time'     => $periodTiming['end']   ?? null,
            'period_duration_min' => $this->whenLoaded('period', fn () => $this->period?->duration_minutes),
            'is_break'            => $this->whenLoaded('period', fn () => (bool) $this->period?->is_break, false),

            // ── Subject ───────────────────────────────────────────────────────────
            'subject_id'    => $this->whenLoaded('assignment', fn () => $this->assignment?->subject_id),
            'subject_name'  => $this->whenLoaded('assignment', fn () => $this->assignment?->subject?->name),
            'subject_color' => $subjectColor,

            // ── Teacher ───────────────────────────────────────────────────────────
            'teacher_id'        => $teacherId,
            'teacher_name'      => $teacherShort,   // Compact: "Mrs. Adaeze O."
            'teacher_full_name' => $teacherFull,    // Full: "Mrs. Adaeze Okonkwo"

            // ── TCSS reference ────────────────────────────────────────────────────
            'tcss_id' => $this->teacher_class_section_subject_id,

            // ── Placement flags ───────────────────────────────────────────────────
            'is_manually_placed' => (bool) $this->is_manually_placed,
            // can_move = slot is manually placed (can always unlock) OR it's auto
            // AND the timetable is draft (can be overwritten by re-generation).
            // The frontend shows a lock icon on manually placed slots.
            'can_move' => (bool) $timetableIsDraft,

            // ── Notes ─────────────────────────────────────────────────────────────
            'notes' => $this->notes,

            // ── Timestamps ───────────────────────────────────────────────────────
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Resolve teacher display names from the nested assignment → teacher → profile chain.
     *
     * Returns an array: [shortName, fullName, teacherId].
     *
     * Short name: "{title} {first_name} {last_name_initial}."
     *   e.g. "Mrs. Adaeze O."
     * Full name: "{title} {first_name} {last_name}"
     *   e.g. "Mrs. Adaeze Okonkwo"
     *
     * Falls back to "TBA" if no teacher is assigned (assignment exists but teacher
     * is not yet filled — valid state for NO_TEACHER_ASSIGNED conflicts).
     *
     * @return array{0: string|null, 1: string|null, 2: int|null}
     */
    private function resolveTeacherNames(): array
    {
        if (! $this->relationLoaded('assignment') || ! $this->assignment) {
            return [null, null, null];
        }

        $assignment = $this->assignment;

        // Try to get teacher data — structure depends on how your Teacher/Staff model
        // exposes its name. We try profile first, then direct attributes.
        $teacher = $assignment->teacher ?? null;
        if (! $teacher) {
            return ['TBA', 'Teacher Not Assigned', null];
        }

        $teacherId = $teacher->id;

        // Profile-based name (most common pattern in this codebase)
        $profile = $teacher->profile ?? $teacher->primaryProfile ?? null;

        if ($profile) {
            $title     = $profile->title ? "{$profile->title} " : '';
            $firstName = $profile->first_name ?? '';
            $lastName  = $profile->last_name  ?? '';
            $lastInit  = $lastName ? strtoupper(substr($lastName, 0, 1)) . '.' : '';

            $shortName = trim("{$title}{$firstName} {$lastInit}") ?: 'Teacher';
            $fullName  = trim("{$title}{$firstName} {$lastName}")  ?: 'Teacher';

            return [$shortName, $fullName, $teacherId];
        }

        // Fallback: direct name attribute (if model exposes $teacher->full_name)
        $fullName  = $teacher->full_name ?? $teacher->name ?? 'Teacher';
        $parts     = explode(' ', $fullName, 2);
        $shortName = $parts[0] . (isset($parts[1]) ? ' ' . strtoupper(substr($parts[1], 0, 1)) . '.' : '');

        return [$shortName, $fullName, $teacherId];
    }

    /**
     * Derive a deterministic hex color from a UUID/string ID.
     *
     * Used when a subject has no explicit color set. Produces consistent colors
     * per subject across page loads by hashing the ID to an HSL value with
     * high saturation and mid lightness (readable on white and dark backgrounds).
     *
     * @param  string  $id
     * @return string  e.g. "#6366f1"
     */
    private function deriveColorFromId(string $id): string
    {
        // Hash the ID to a number and map to hue (0–359)
        $hash = hexdec(substr(md5($id), 0, 6));
        $hue  = $hash % 360;

        // Fixed saturation 60%, lightness 50% — readable in both light/dark modes
        return $this->hslToHex($hue, 60, 50);
    }

    /**
     * Convert HSL to a hex color string.
     *
     * @param  int  $h  Hue 0–360
     * @param  int  $s  Saturation 0–100
     * @param  int  $l  Lightness 0–100
     * @return string
     */
    private function hslToHex(int $h, int $s, int $l): string
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60  => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default  => [$c, 0, $x],
        };

        $toHex = fn (float $v): string => str_pad(dechex((int) round(($v + $m) * 255)), 2, '0', STR_PAD_LEFT);

        return '#' . $toHex($r) . $toHex($g) . $toHex($b);
    }
}
