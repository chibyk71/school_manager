<?php

namespace App\Rules\Academic;

use App\Models\Academic\TimetableSlot;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * NoSectionConflict — Custom Validation Rule for Timetable Slot Section Availability
 *
 * ── What This Does ────────────────────────────────────────────────────────────────
 * Ensures a class section (arm) is not already assigned to a timetable slot on the
 * same timetable, day, and period before the slot is created or updated.
 *
 * A DB unique constraint on `(timetable_id, class_section_id, class_period_id,
 * day_of_week)` would enforce this at the database level, but raw constraint
 * violations produce a generic PDO exception with no user-friendly message.
 * This rule catches the conflict BEFORE the INSERT/UPDATE, returns a clean 422,
 * and surfaces a readable error that the frontend can display in context.
 *
 * ── Why It Exists ────────────────────────────────────────────────────────────────
 * • Provides a clear, actionable 422 error instead of a 500 from a DB constraint.
 * • Works symmetrically with NoTeacherConflict — together they cover the two core
 *   conflict types that can occur on manual slot placement.
 * • Handles the UPDATE case correctly by excluding the slot being edited from the
 *   conflict scan (via `$excludeSlotId`).
 * • Implements `DataAwareRule` so it can access `timetable_id`, `class_period_id`,
 *   and `day_of_week` from the request without constructor injection.
 *
 * ── How It Fits Into the Module ───────────────────────────────────────────────────
 * • Used in `StoreTimetableSlotRequest::rules()` on the `class_section_id` field.
 * • The DB unique constraint still exists as the final safety net (it should never
 *   be hit if this rule is wired correctly, but defense in depth).
 * • TimetableGeneratorService checks conflicts programmatically and does NOT use
 *   this rule — it writes to `timetable_conflicts` instead.
 *
 * ── Usage ─────────────────────────────────────────────────────────────────────────
 *   'class_section_id' => [
 *       'required',
 *       'exists:class_sections,id',
 *       new NoSectionConflict(),               // create
 *       new NoSectionConflict($slot->id),      // update (exclude self)
 *   ],
 *
 * ── Data Requirements (from request payload) ─────────────────────────────────────
 *   - timetable_id      (UUID)   — which timetable to check within
 *   - class_period_id   (int)    — which period slot in the day
 *   - day_of_week       (int)    — ISO 8601: 1=Mon … 7=Sun
 *
 * ── Error Message Format ──────────────────────────────────────────────────────────
 * Returns a user-friendly message that names the subject already booked in the
 * conflicting slot, giving the admin immediately actionable information.
 */
class NoSectionConflict implements ValidationRule, DataAwareRule
{
    /**
     * The full request data, injected by Laravel via DataAwareRule.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  string|null  $excludeSlotId  UUID of the slot being updated; null on create.
     */
    public function __construct(
        protected readonly ?string $excludeSlotId = null
    ) {}

    /**
     * Receive full request data from Laravel's validator.
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
     * @param  string   $attribute  Field being validated (class_section_id)
     * @param  mixed    $value      The class section UUID submitted by the client
     * @param  Closure  $fail       Call to fail with a user-facing message
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ── Guard: skip if companion fields are missing ───────────────────────────────
        $timetableId   = $this->data['timetable_id']    ?? null;
        $classPeriodId = $this->data['class_period_id'] ?? null;
        $dayOfWeek     = $this->data['day_of_week']     ?? null;

        if (! $timetableId || ! $classPeriodId || $dayOfWeek === null) {
            return;
        }

        // ── Build conflict query replicating the DB unique constraint ─────────────────
        $conflictQuery = TimetableSlot::query()
            ->where('timetable_id',      $timetableId)
            ->where('class_section_id',  $value)
            ->where('class_period_id',   $classPeriodId)
            ->where('day_of_week',       (int) $dayOfWeek);

        // Exclude the current slot when updating so it doesn't conflict with itself
        if ($this->excludeSlotId) {
            $conflictQuery->where('id', '!=', $this->excludeSlotId);
        }

        // Eager-load relationship data for the error message in a single query
        $conflictingSlot = $conflictQuery
            ->with(['assignment.subject:id,name'])
            ->first();

        if (! $conflictingSlot) {
            return; // No conflict — slot is available
        }

        // ── Build a meaningful error message ──────────────────────────────────────────
        $subjectName = $conflictingSlot->assignment?->subject?->name ?? 'another subject';

        $fail(
            "This class section already has {$subjectName} scheduled on this day and period. " .
            "Each section can only have one lesson per period. Please choose a different period."
        );
    }
}
