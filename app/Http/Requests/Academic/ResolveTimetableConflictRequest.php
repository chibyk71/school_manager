<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\ClassPeriod;
use App\Models\Academic\Timetable;
use App\Models\Academic\TimetableConflict;
use App\Rules\Academic\NoSectionConflict;
use App\Rules\Academic\NoTeacherConflict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ResolveTimetableConflictRequest
 *
 * Validates the payload that resolves a TimetableConflict record by assigning
 * a specific lesson slot to replace the unresolvable generator attempt.
 *
 * ── What "Resolution" Means ───────────────────────────────────────────────────
 * Admin opens the ConflictPanel, sees a conflict, and either:
 *
 *   (A) Picks a generator suggestion:
 *       { "resolution_strategy": "use_suggestion", "suggestion_index": 0, ... }
 *
 *   (B) Manually specifies a slot:
 *       { "resolution_strategy": "manual", "class_period_id": 5, "day_of_week": 2, ... }
 *
 *   (C) Marks it as intentionally skipped (no lesson needed):
 *       { "resolution_strategy": "skip", "resolution_notes": "Subject removed from curriculum." }
 *
 * ── Request Shape ─────────────────────────────────────────────────────────────
 * {
 *   "resolution_strategy":            "use_suggestion" | "manual" | "skip",
 *   "suggestion_index":               0,               // only for use_suggestion
 *   "class_period_id":                5,               // only for manual
 *   "day_of_week":                    2,               // only for manual
 *   "teacher_class_section_subject_id": 12,            // only for manual (optional override)
 *   "resolution_notes":               "Moved to avoid Abubakar's JSS clash."
 * }
 *
 * ── Resolution Strategies ─────────────────────────────────────────────────────
 *
 *   use_suggestion:
 *     Takes the slot details from the conflict's suggested_alternatives array
 *     at the given index. TimetableService validates the suggestion is still
 *     conflict-free at execution time (race condition protection).
 *
 *   manual:
 *     Admin specifies a class_period_id + day_of_week. The teacher assignment
 *     is taken from the conflict's teacher_class_section_subject_id unless
 *     teacher_class_section_subject_id is also submitted (teacher swap).
 *     Runs full conflict checks (NoSectionConflict + NoTeacherConflict).
 *
 *   skip:
 *     Marks the conflict as resolved without creating a slot.
 *     Used when a subject has been dropped, or the frequency requirement is
 *     being intentionally reduced.
 *     resolution_notes is REQUIRED for skip — admin must document the reason.
 *
 * ── Conflict Must Be Unresolved ───────────────────────────────────────────────
 * Attempting to resolve an already-resolved conflict is rejected in withValidator().
 *
 * ── Conflict Must Belong to a Draft Timetable ────────────────────────────────
 * Conflicts on active/archived timetables cannot be retroactively resolved.
 * Validated in withValidator().
 *
 * ── no_teacher_assigned Special Case ─────────────────────────────────────────
 * This conflict type requires the admin to supply a teacher_class_section_subject_id
 * explicitly (because the original conflict had none). Validated in withValidator().
 */
class ResolveTimetableConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $strategy = $this->input('resolution_strategy');
        $schoolId = auth()->user()?->school_id;

        // Resolve the conflict early to use its data in dependent rules.
        /** @var TimetableConflict|null $conflict */
        $conflict = $this->route('timetableConflict');

        // Derive the teacher_class_section_subject_id that will be used.
        // For manual strategy admin can submit an override; otherwise fall back
        // to the conflict's own TCSS ID.
        $tcssId = $this->input(
            'teacher_class_section_subject_id',
            $conflict?->teacher_class_section_subject_id
        );

        return [
            // ── Strategy ─────────────────────────────────────────────────────

            'resolution_strategy' => [
                'required',
                Rule::in(['use_suggestion', 'manual', 'skip']),
            ],

            // ── use_suggestion fields ─────────────────────────────────────────

            'suggestion_index' => [
                Rule::requiredIf($strategy === 'use_suggestion'),
                'nullable',
                'integer',
                'min:0',
                // Upper bound validated in withValidator() against the actual
                // suggestions array count.
            ],

            // ── manual fields ─────────────────────────────────────────────────

            'class_period_id' => [
                Rule::requiredIf($strategy === 'manual'),
                'nullable',
                'integer',
                Rule::exists('class_periods', 'id')->where('school_id', $schoolId),
                // Conflict checks run in withValidator() once we have all values.
            ],

            'day_of_week' => [
                Rule::requiredIf($strategy === 'manual'),
                'nullable',
                'integer',
                'min:1',
                'max:7',
            ],

            'teacher_class_section_subject_id' => [
                // Required only when the original conflict had no TCSS (no_teacher_assigned).
                // For other strategies/types it is optional (falls back to conflict's own TCSS).
                Rule::requiredIf(
                    $strategy !== 'skip' &&
                    $conflict?->conflict_type === TimetableConflict::TYPE_NO_TEACHER_ASSIGNED
                ),
                'nullable',
                'integer',
                Rule::exists('teacher_class_section_subjects', 'id'),
            ],

            // ── Shared fields ─────────────────────────────────────────────────

            'resolution_notes' => [
                // Required for 'skip' — admin must document why the subject is
                // being intentionally left unscheduled.
                Rule::requiredIf($strategy === 'skip'),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Cross-field and state validations.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return; // Field-level errors take priority.
            }

            /** @var TimetableConflict|null $conflict */
            $conflict = $this->route('timetableConflict');
            $strategy = $this->input('resolution_strategy');

            if (! $conflict) {
                $v->errors()->add('timetableConflict', 'Conflict record not found.');
                return;
            }

            // ── Check 1: Conflict must still be unresolved ────────────────────
            if ($conflict->isResolved()) {
                $v->errors()->add(
                    'resolution_strategy',
                    'This conflict has already been resolved and cannot be modified.'
                );
                return;
            }

            // ── Check 2: Parent timetable must be in draft status ─────────────
            $timetable = $conflict->timetable;

            if ($timetable && ! $timetable->isDraft()) {
                $v->errors()->add(
                    'resolution_strategy',
                    "Conflicts on {$timetable->status} timetables cannot be resolved. " .
                    "Only draft timetable conflicts can be resolved."
                );
                return;
            }

            // ── Strategy-specific checks ──────────────────────────────────────

            if ($strategy === 'use_suggestion') {
                $this->validateUseSuggestion($v, $conflict);
            }

            if ($strategy === 'manual') {
                $this->validateManual($v, $conflict, $timetable);
            }
            // 'skip' has no additional cross-field checks beyond resolution_notes.
        });
    }

    /**
     * Validate the 'use_suggestion' strategy: suggestion must exist and still
     * be conflict-free at the time of submission.
     */
    private function validateUseSuggestion(
        \Illuminate\Validation\Validator $v,
        TimetableConflict $conflict
    ): void {
        $index = (int) $this->input('suggestion_index', 0);
        $suggestion = $conflict->getSuggestion($index);

        if (! $suggestion) {
            $v->errors()->add(
                'suggestion_index',
                ! $conflict->hasSuggestions()
                    ? 'This conflict has no generator suggestions. Use the manual strategy instead.'
                    : "Suggestion index {$index} does not exist for this conflict."
            );
            return;
        }

        // Re-run conflict checks on the suggestion to catch race conditions
        // (another admin may have placed a slot at this position since generation).
        $timetableId  = $conflict->timetable_id;
        $periodId     = (int) ($suggestion['class_period_id'] ?? 0);
        $dayOfWeek    = (int) ($suggestion['day_of_week'] ?? 0);
        $tcssId       = (int) ($suggestion['teacher_class_section_subject_id']
                            ?? $conflict->teacher_class_section_subject_id
                            ?? 0);

        if (TimetableSlot::query()
            ->where('timetable_id', $timetableId)
            ->where('class_section_id', $conflict->class_section_id)
            ->where('class_period_id', $periodId)
            ->where('day_of_week', $dayOfWeek)
            ->whereNull('deleted_at')
            ->exists()
        ) {
            $v->errors()->add(
                'suggestion_index',
                'This suggestion is no longer available — the slot has been filled since this conflict was generated. ' .
                'Please refresh and choose another option.'
            );
        }
    }

    /**
     * Validate the 'manual' strategy: period must be assignable, belong to the
     * correct day schedule, and have no section or teacher conflicts.
     */
    private function validateManual(
        \Illuminate\Validation\Validator $v,
        TimetableConflict $conflict,
        ?Timetable $timetable
    ): void {
        $periodId  = (int) $this->input('class_period_id');
        $dayOfWeek = (int) $this->input('day_of_week');
        $tcssId    = (int) $this->input(
            'teacher_class_section_subject_id',
            $conflict->teacher_class_section_subject_id
        );

        if (! $periodId || ! $dayOfWeek) {
            return; // Field-level required rules already handle these.
        }

        // ── Period must not be a break ─────────────────────────────────────
        $period = ClassPeriod::find($periodId);

        if ($period && $period->is_break) {
            $v->errors()->add(
                'class_period_id',
                "\"{$period->name}\" is a break period and cannot hold a lesson."
            );
            return;
        }

        // ── Period must belong to the day's schedule ───────────────────────
        if ($timetable && $period) {
            $daySchedule = $timetable->daySchedules()
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if (! $daySchedule) {
                $v->errors()->add(
                    'day_of_week',
                    "Day {$dayOfWeek} is not a working day for this timetable."
                );
                return;
            }

            if ($period->period_schedule_id !== $daySchedule->period_schedule_id) {
                $v->errors()->add(
                    'class_period_id',
                    "Period \"{$period->name}\" does not belong to the schedule for day {$dayOfWeek}."
                );
                return;
            }
        }

        // ── Section conflict check ─────────────────────────────────────────
        $sectionConflictExists = \App\Models\Academic\TimetableSlot::where('timetable_id', $conflict->timetable_id)
            ->where('class_section_id', $conflict->class_section_id)
            ->where('class_period_id', $periodId)
            ->where('day_of_week', $dayOfWeek)
            ->whereNull('deleted_at')
            ->exists();

        if ($sectionConflictExists) {
            $v->errors()->add(
                'class_period_id',
                'This class section already has a lesson assigned at this period and day.'
            );
        }

        // ── Teacher conflict check ─────────────────────────────────────────
        if ($tcssId && \App\Models\Academic\TimetableSlot::teacherConflictExists(
            timetableId:                   $conflict->timetable_id,
            teacherClassSectionSubjectId:  $tcssId,
            classPeriodId:                 $periodId,
            dayOfWeek:                     $dayOfWeek,
        )) {
            $v->errors()->add(
                'teacher_class_section_subject_id',
                'The assigned teacher is already teaching another class at this period and day.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'resolution_strategy.required'              => 'Please specify how you want to resolve this conflict.',
            'resolution_strategy.in'                    => 'Resolution strategy must be one of: use_suggestion, manual, or skip.',
            'suggestion_index.required'                 => 'Please specify which suggestion to use.',
            'suggestion_index.integer'                  => 'Suggestion index must be a number.',
            'class_period_id.required'                  => 'Please select a period for the manual resolution.',
            'day_of_week.required'                      => 'Please select a day for the manual resolution.',
            'day_of_week.min'                           => 'Day of week must be between 1 (Monday) and 7 (Sunday).',
            'day_of_week.max'                           => 'Day of week must be between 1 (Monday) and 7 (Sunday).',
            'teacher_class_section_subject_id.required' => 'A teacher assignment is required for this conflict type.',
            'resolution_notes.required'                 => 'Please provide a reason when skipping a conflict.',
            'resolution_notes.max'                      => 'Resolution notes may not exceed 500 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'resolution_strategy'              => 'resolution strategy',
            'suggestion_index'                 => 'suggestion',
            'class_period_id'                  => 'period',
            'day_of_week'                      => 'day of week',
            'teacher_class_section_subject_id' => 'teacher assignment',
            'resolution_notes'                 => 'resolution notes',
        ];
    }
}
