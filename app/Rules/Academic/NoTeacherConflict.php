<?php

namespace App\Rules\Academic;

use App\Models\Academic\TimetableSlot;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * NoTeacherConflict — Custom Validation Rule for Timetable Slot Teacher Availability
 *
 * ── What This Does ────────────────────────────────────────────────────────────────
 * Ensures the teacher embedded in a TeacherClassSectionSubject (TCSS) assignment is
 * not already booked in another timetable slot on the same timetable, day, and period.
 *
 * Unlike a section conflict (which maps 1:1 to a DB unique constraint), teacher
 * conflicts CANNOT be enforced at the DB level because the teacher identifier lives
 * inside the `teacher_class_section_subject_id` foreign key — not in a dedicated
 * `teacher_id` column on `timetable_slots`. We therefore do this check at the
 * application layer using `TimetableSlot::teacherConflictExists()`.
 *
 * ── Why It Exists ────────────────────────────────────────────────────────────────
 * • Prevents double-booking: a teacher cannot be in two classrooms at the same time.
 * • Works for both CREATE (new slot) and UPDATE (moving/editing a slot):
 *   - On create: no exclude ID needed.
 *   - On update: the current slot's ID is excluded so we don't flag itself.
 * • Returns a clear 422 with the conflicting slot's details for frontend display.
 * • Implements `DataAwareRule` so it can read the full request payload (timetable_id,
 *   class_period_id, day_of_week) without needing them passed via constructor.
 *
 * ── How It Fits Into the Module ───────────────────────────────────────────────────
 * • Used in `StoreTimetableSlotRequest::rules()` as a rule on the
 *   `teacher_class_section_subject_id` field.
 * • TimetableGeneratorService does NOT use this rule — it uses
 *   `TimetableSlot::teacherConflictExists()` directly for performance.
 * • Only invoked on manual slot placement (builder drag-drop or direct API call).
 *
 * ── Usage ─────────────────────────────────────────────────────────────────────────
 *   'teacher_class_section_subject_id' => [
 *       'required',
 *       'exists:teacher_class_section_subjects,id',
 *       new NoTeacherConflict(),               // create
 *       new NoTeacherConflict($slot->id),      // update (exclude self)
 *   ],
 *
 * ── Data Requirements (from request payload) ─────────────────────────────────────
 *   - timetable_id      (UUID)   — which timetable to check within
 *   - class_period_id   (int)    — which period slot in the day
 *   - day_of_week       (int)    — ISO 8601: 1=Mon … 7=Sun
 *
 * ── Error Message Format ──────────────────────────────────────────────────────────
 * Returns a user-friendly message referencing the conflicting class section so the
 * admin can quickly identify which arm is causing the clash.
 */
class NoTeacherConflict implements ValidationRule, DataAwareRule
{
    /**
     * The full request data, injected by Laravel via DataAwareRule.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  string|null  $excludeSlotId  UUID of the slot being updated; null on create.
     *                                      Excludes the slot from its own conflict check.
     */
    public function __construct(
        protected readonly ?string $excludeSlotId = null
    ) {}

    /**
     * Receive full request data from Laravel's validator.
     *
     * Called automatically before `validate()` because we implement DataAwareRule.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string   $attribute  Field being validated (teacher_class_section_subject_id)
     * @param  mixed    $value      The TCSS UUID submitted by the client
     * @param  Closure  $fail       Call to fail with a user-facing message
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ── Guard: skip if companion fields are missing (let required rule handle them) ──
        $timetableId   = $this->data['timetable_id']    ?? null;
        $classPeriodId = $this->data['class_period_id'] ?? null;
        $dayOfWeek     = $this->data['day_of_week']     ?? null;

        if (! $timetableId || ! $classPeriodId || $dayOfWeek === null) {
            return;
        }

        // ── Core check via the static model method ────────────────────────────────────
        $conflict = TimetableSlot::teacherConflictExists(
            timetableId:    $timetableId,
            tcssId:         $value,
            periodId:       $classPeriodId,
            dayOfWeek:      (int) $dayOfWeek,
            excludeSlotId:  $this->excludeSlotId,
        );

        if (! $conflict) {
            return; // All clear
        }

        // ── Build a meaningful error message ──────────────────────────────────────────
        // Load the conflicting slot with its class section so we can name it.
        // We use withTrashed() defensively — a soft-deleted slot shouldn't cause
        // conflicts, but being explicit prevents silent false negatives.
        $conflictingSlot = TimetableSlot::with([
            'classSection:id,display_name,name',
            'assignment.subject:id,name',
        ])
            ->where('timetable_id', $timetableId)
            ->where('teacher_class_section_subject_id', $value)
            ->where('class_period_id', $classPeriodId)
            ->where('day_of_week', (int) $dayOfWeek)
            ->when($this->excludeSlotId, fn ($q) => $q->where('id', '!=', $this->excludeSlotId))
            ->first();

        if ($conflictingSlot) {
            $sectionName = $conflictingSlot->classSection?->display_name
                ?? $conflictingSlot->classSection?->name
                ?? 'another class';

            $subjectName = $conflictingSlot->assignment?->subject?->name ?? 'another subject';

            $fail(
                "This teacher is already assigned to {$sectionName} for {$subjectName} " .
                "on this day and period. Please choose a different period or teacher."
            );
        } else {
            // Conflict confirmed but details unavailable (race condition / soft delete edge case)
            $fail(
                "This teacher already has a booking on this day and period. " .
                "Please choose a different period or teacher."
            );
        }
    }
}
