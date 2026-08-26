<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\ClassPeriod;
use App\Models\Academic\Timetable;
use App\Models\Academic\TimetableSlot;
use App\Rules\Academic\NoSectionConflict;
use App\Rules\Academic\NoTeacherConflict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreTimetableSlotRequest
 *
 * Validates creating or updating a single TimetableSlot.
 * Replaces the original StoreTimeTableDetailRequest.
 *
 * ── Request Shape ─────────────────────────────────────────────────────────────
 * {
 *   "timetable_id":                     "uuid",
 *   "class_section_id":                 "uuid",
 *   "class_period_id":                  5,
 *   "teacher_class_section_subject_id": 12,
 *   "day_of_week":                      3,
 *   "is_manually_placed":               true,
 *   "notes":                            "Lab session — use Science block B"
 * }
 *
 * ── Two-Level Conflict Checking ────────────────────────────────────────────────
 *
 * Section conflict (NoSectionConflict rule):
 *   "Does SSS 1A already have a subject assigned to Period 5 on Wednesday?"
 *   This duplicates the DB unique constraint as an application-level check so the
 *   response is a 422 with a clear message rather than a 500 / IntegrityException.
 *
 * Teacher conflict (NoTeacherConflict rule):
 *   "Is Mr. Abubakar already teaching another class at Period 5 on Wednesday?"
 *   This CANNOT be expressed as a DB unique constraint because the teacher_id
 *   is embedded inside teacher_class_section_subjects (not on the slot row).
 *   The rule extracts the teacher_id from the TCSS row and checks all other
 *   slots at the same period/day in the same timetable.
 *
 * Both rules receive the current slot's ID on update so they can correctly
 * exclude the row being edited from conflict checks.
 *
 * ── Bugs Fixed From Original ──────────────────────────────────────────────────
 * Original `StoreTimeTableDetailRequest` issues:
 *   1. Unique rule was scoped on `start_time` (not stored) instead of `class_period_id`.
 *      Fixed: section conflict is now checked via `NoSectionConflict` rule.
 *   2. `after:start_time` was a string comparison, not a time comparison.
 *      Fixed: start_time and end_time are no longer stored — computed from period.
 *   3. `day` was validated as a day-name string ("Monday") instead of an ISO integer.
 *      Fixed: `day_of_week` is now an integer 1–7.
 *   4. No teacher double-booking check at all.
 *      Fixed: `NoTeacherConflict` custom rule.
 *   5. Called `GetSchoolModel()` directly.
 *      Fixed: `auth()->user()->school_id`.
 *
 * ── Timetable Must Be Draft ───────────────────────────────────────────────────
 * Slots can only be added to draft timetables. Active and archived timetables
 * are read-only. Validated in `withValidator()`.
 *
 * ── Period Must Be a Lesson Period ───────────────────────────────────────────
 * Break periods (is_break = true) are not assignable. Validated in `withValidator()`.
 *
 * ── Period Must Belong to This Timetable's Day Schedule ──────────────────────
 * The period must belong to the PeriodSchedule mapped to the requested day_of_week
 * in this timetable's day schedules. Validated in `withValidator()`.
 * This prevents assigning Period 3 from "Regular Day" to a Friday that uses
 * the "Short Friday" schedule with a different set of periods.
 *
 * ── class_section_id Must Match the TCSS Assignment ─────────────────────────
 * The class_section_id submitted must match the class_section_id on the
 * referenced TeacherClassSectionSubject row. They must be consistent.
 * Validated in `withValidator()`.
 */
class StoreTimetableSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $schoolId   = auth()->user()?->school_id;
        $slotId     = optional($this->route('timetableSlot'))->id; // null on create

        return [
            'timetable_id' => [
                'required',
                'uuid',
                Rule::exists('timetables', 'id')->where('school_id', $schoolId),
            ],

            'class_section_id' => [
                'required',
                'uuid',
                Rule::exists('class_sections', 'id')->where('school_id', $schoolId),
            ],

            'class_period_id' => [
                'required',
                'integer',
                Rule::exists('class_periods', 'id')->where('school_id', $schoolId),
            ],

            'teacher_class_section_subject_id' => [
                'required',
                'integer',
                Rule::exists('teacher_class_section_subjects', 'id'),
                // Cross-check with class_section_id done in withValidator().
            ],

            'day_of_week' => [
                'required',
                'integer',
                'min:1',
                'max:7',
                // ISO 8601: 1=Monday, 2=Tuesday ... 5=Friday, 6=Saturday, 7=Sunday
                // Must correspond to a day that has a timetable_day_schedule row.
                // Validated in withValidator().
            ],

            'is_manually_placed' => [
                'boolean',
                // When the builder UI places a slot, this should be true.
                // Generator-placed slots set this to false.
                // Defaults to false if not provided (prepareForValidation sets it).
            ],

            'notes' => [
                'nullable',
                'string',
                'max:255',
            ],

            // ── Conflict Rules ────────────────────────────────────────────────
            // These rules receive IDs resolved from the other fields.
            // They run after field-level validation succeeds so they can safely
            // read validated input values.

            'class_section_id' => [
                'required',
                'uuid',
                Rule::exists('class_sections', 'id')->where('school_id', $schoolId),
                new NoSectionConflict(
                    timetableId:   $this->input('timetable_id'),
                    classPeriodId: (int) $this->input('class_period_id'),
                    dayOfWeek:     (int) $this->input('day_of_week'),
                    excludeSlotId: $slotId,
                ),
            ],

            'teacher_class_section_subject_id' => [
                'required',
                'integer',
                Rule::exists('teacher_class_section_subjects', 'id'),
                new NoTeacherConflict(
                    timetableId:   $this->input('timetable_id'),
                    classPeriodId: (int) $this->input('class_period_id'),
                    dayOfWeek:     (int) $this->input('day_of_week'),
                    excludeSlotId: $slotId,
                ),
            ],
        ];
    }

    /**
     * Cross-field validations that require multiple resolved values.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            // Stop early if field-level errors already exist — the checks below
            // require valid FK values that may not exist yet if fields failed.
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $timetableId  = $this->input('timetable_id');
            $periodId     = (int) $this->input('class_period_id');
            $dayOfWeek    = (int) $this->input('day_of_week');
            $tcssId       = (int) $this->input('teacher_class_section_subject_id');
            $sectionId    = $this->input('class_section_id');

            // ── Check 1: Timetable must be in draft status ────────────────────
            $timetable = Timetable::find($timetableId);

            if ($timetable && ! $timetable->isDraft()) {
                $v->errors()->add(
                    'timetable_id',
                    "Slots can only be added to draft timetables. " .
                    "This timetable is currently '{$timetable->status}'."
                );
                return; // No point checking further.
            }

            // ── Check 2: Period must be a lesson (not a break) ────────────────
            $period = ClassPeriod::find($periodId);

            if ($period && $period->is_break) {
                $v->errors()->add(
                    'class_period_id',
                    "Break periods cannot be assigned to lessons. " .
                    "\"{$period->name}\" is a break period."
                );
            }

            // ── Check 3: Period must belong to the day's schedule ─────────────
            // The period_schedule_id of the class_period must match the
            // period_schedule_id mapped to day_of_week in timetable_day_schedules.
            if ($timetable && $period) {
                $daySchedule = $timetable->daySchedules()
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if (! $daySchedule) {
                    $v->errors()->add(
                        'day_of_week',
                        "Day {$dayOfWeek} is not a working day for this timetable. " .
                        "Add a day schedule mapping first."
                    );
                } elseif ($period->period_schedule_id !== $daySchedule->period_schedule_id) {
                    $v->errors()->add(
                        'class_period_id',
                        "Period \"{$period->name}\" does not belong to the schedule " .
                        "configured for this day. Use periods from the correct day schedule."
                    );
                }
            }

            // ── Check 4: class_section_id must match the TCSS assignment ─────
            // The submitted class_section_id must match the class_section_id
            // on the referenced teacher_class_section_subjects row.
            if ($tcssId && $sectionId) {
                $tcss = \App\Models\Academic\TeacherClassSectionSubject::find($tcssId);

                if ($tcss && (string) $tcss->class_section_id !== (string) $sectionId) {
                    $v->errors()->add(
                        'teacher_class_section_subject_id',
                        'The selected teacher assignment does not belong to the specified class section.'
                    );
                }
            }
        });
    }

    /**
     * Set sensible defaults before validation runs.
     */
    protected function prepareForValidation(): void
    {
        // Default is_manually_placed to true when coming from the UI builder.
        // The generator sets this explicitly to false when creating auto slots.
        if (! $this->has('is_manually_placed')) {
            $this->merge(['is_manually_placed' => true]);
        }
    }

    public function messages(): array
    {
        return [
            'timetable_id.exists'                             => 'The selected timetable does not exist.',
            'class_section_id.exists'                         => 'The selected class section does not belong to your school.',
            'class_period_id.exists'                          => 'The selected period does not exist for your school.',
            'teacher_class_section_subject_id.exists'         => 'The selected teacher assignment does not exist.',
            'day_of_week.min'                                 => 'Day of week must be between 1 (Monday) and 7 (Sunday).',
            'day_of_week.max'                                 => 'Day of week must be between 1 (Monday) and 7 (Sunday).',
            'notes.max'                                       => 'Slot notes may not exceed 255 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'timetable_id'                     => 'timetable',
            'class_section_id'                 => 'class section',
            'class_period_id'                  => 'period',
            'teacher_class_section_subject_id' => 'teacher assignment',
            'day_of_week'                      => 'day of week',
            'is_manually_placed'               => 'manual placement flag',
        ];
    }
}
