<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\Timetable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreTimetableRequest
 *
 * Validates creating or updating a Timetable header record.
 *
 * ── Request Shape ─────────────────────────────────────────────────────────────
 * {
 *   "school_section_id": "uuid",
 *   "term_id":           1,
 *   "title":             "SSS Term 1 2025/2026",
 *   "effective_from":    "2025-09-08",
 *   "effective_to":      null,
 *   "status":            "draft",
 *   "notes":             "Revised after staff changes in Physics.",
 *   "options": {
 *     "max_periods_per_teacher_per_day": 4,
 *     "working_days": [1, 2, 3, 4, 5],
 *     "distribute_subjects_evenly": true
 *   },
 *   "day_schedules": [
 *     { "day_of_week": 1, "period_schedule_id": 3 },
 *     { "day_of_week": 2, "period_schedule_id": 3 },
 *     { "day_of_week": 3, "period_schedule_id": 3 },
 *     { "day_of_week": 4, "period_schedule_id": 3 },
 *     { "day_of_week": 5, "period_schedule_id": 7 }
 *   ]
 * }
 *
 * ── Key Validation Decisions ──────────────────────────────────────────────────
 * - Creating a second ACTIVE timetable for the same (school_section, term) is
 *   blocked at the request level with a clear error message. Draft timetables
 *   are allowed to coexist — only active ones are restricted.
 *
 *   WHY here and not only in TimetableService::activate()?
 *   When the request itself has status=active (admin skips draft and activates
 *   directly), we catch it early with a meaningful validation error rather than
 *   letting the service throw a RuntimeException that surfaces as a 500.
 *
 * - `effective_from` must be a valid date. `effective_to` when present must be
 *   on or after `effective_from`. Using date_format validation avoids accepting
 *   ambiguous locale-specific strings.
 *
 * - `day_schedules` is optional on create/update — admin can configure day
 *   mappings separately after creating the header. The timetable builder has
 *   its own step for this. If provided, it is validated fully here.
 *
 * - `options` keys are validated individually to give useful error messages
 *   rather than treating the JSON blob as opaque.
 *
 * ── Bugs Fixed From Original ──────────────────────────────────────────────────
 * Original `StoreTimeTableRequest` issues:
 *   1. Called `GetSchoolModel()` directly — breaks in tests and queue workers.
 *      Fixed: use `auth()->user()->school_id`.
 *   2. `unique` rule used raw string interpolation — fragile and SQL-injection
 *      adjacent. Fixed: use `Rule::unique()` fluent builder.
 *   3. No check for existing active timetable on same section/term.
 *      Fixed: `withValidator()` hook below.
 *   4. Had `school_sections` as an array — the new design is one section per
 *      timetable. Fixed: singular `school_section_id`.
 */
class StoreTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $timetableId = optional($this->route('timetable'))->id;
        $schoolId    = auth()->user()?->school_id;

        return [
            // ── Core Identity ─────────────────────────────────────────────────

            'school_section_id' => [
                'required',
                'uuid',
                Rule::exists('school_sections', 'id')->where('school_id', $schoolId),
                // Scoped to the authenticated school — prevents cross-school section refs.
            ],

            'term_id' => [
                'required',
                'integer',
                Rule::exists('terms', 'id')->where('school_id', $schoolId),
            ],

            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('timetables', 'title')
                    ->where('school_id', $schoolId)
                    ->ignore($timetableId),
                // Unique within the school, not just section/term, to keep admin
                // listing pages unambiguous.
            ],

            // ── Dates ────────────────────────────────────────────────────────

            'effective_from' => [
                'required',
                'date_format:Y-m-d',
                // Accepts ISO date strings only — no ambiguous "01/02/2025" strings.
            ],

            'effective_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:effective_from',
                // When provided, must not precede the start date.
                // Active timetables should leave this null (no end date).
            ],

            // ── Status ───────────────────────────────────────────────────────

            'status' => [
                'required',
                Rule::in([
                    Timetable::STATUS_DRAFT,
                    Timetable::STATUS_ACTIVE,
                    Timetable::STATUS_ARCHIVED,
                ]),
                // Note: if status = 'active', the withValidator hook below checks
                // that no other active timetable exists for this section/term.
            ],

            // ── Optional Content ──────────────────────────────────────────────

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            // ── Generator Options ─────────────────────────────────────────────
            // Validated individually so errors are specific rather than opaque.

            'options'                                        => ['nullable', 'array'],
            'options.max_periods_per_teacher_per_day'        => ['nullable', 'integer', 'min:1', 'max:20'],
            'options.working_days'                           => ['nullable', 'array', 'min:1', 'max:7'],
            'options.working_days.*'                         => ['integer', 'min:1', 'max:7'],
            // ISO 8601 day numbers: 1=Monday..7=Sunday
            'options.distribute_subjects_evenly'             => ['nullable', 'boolean'],
            'options.prefer_morning_for_core_subjects'       => ['nullable', 'boolean'],
            'options.max_consecutive_periods_per_teacher'    => ['nullable', 'integer', 'min:1', 'max:10'],

            // ── Day → Schedule Mappings (optional on create/update) ───────────

            'day_schedules'                    => ['nullable', 'array'],
            'day_schedules.*.day_of_week'      => [
                'required_with:day_schedules',
                'integer',
                'min:1',
                'max:7',
            ],
            'day_schedules.*.period_schedule_id' => [
                'required_with:day_schedules',
                'integer',
                Rule::exists('period_schedules', 'id')->where('school_id', $schoolId),
            ],
        ];
    }

    /**
     * Cross-field validation: block creating a second active timetable for the
     * same (school_section_id, term_id) combination.
     *
     * This is checked here (not only in TimetableService::activate()) because
     * when the request itself submits status='active', we want a 422 validation
     * error, not a 500 from a service exception.
     *
     * On update: if the timetable being updated is already the active one for
     * this section/term, editing it is allowed. The conflict only triggers when
     * a DIFFERENT timetable is trying to claim active status.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            // Only relevant when the submitted status is 'active'.
            if ($this->input('status') !== Timetable::STATUS_ACTIVE) {
                return;
            }

            $schoolId       = auth()->user()?->school_id;
            $sectionId      = $this->input('school_section_id');
            $termId         = $this->input('term_id');
            $currentId      = optional($this->route('timetable'))->id;

            if (! $schoolId || ! $sectionId || ! $termId) {
                return; // Let field-level required rules handle these first.
            }

            $existingActive = Timetable::where('school_id', $schoolId)
                ->where('school_section_id', $sectionId)
                ->where('term_id', $termId)
                ->where('status', Timetable::STATUS_ACTIVE)
                ->when($currentId, fn ($q) => $q->whereKeyNot($currentId))
                ->exists();

            if ($existingActive) {
                $v->errors()->add(
                    'status',
                    'An active timetable already exists for this school section and term. ' .
                    'Archive or deactivate it before activating a new one, or save this timetable as draft first.'
                );
            }

            // ── day_schedules uniqueness within the submitted array ────────────
            // Prevent submitting two mappings for the same day_of_week.
            $daySchedules = $this->input('day_schedules', []);

            if (is_array($daySchedules) && count($daySchedules) > 0) {
                $days = array_column($daySchedules, 'day_of_week');
                $validDays = array_filter($days, 'is_numeric');

                if (count($validDays) !== count(array_unique($validDays))) {
                    $v->errors()->add(
                        'day_schedules',
                        'Each day of the week can only be mapped to one period schedule.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'school_section_id.exists'                  => 'The selected school section does not belong to your school.',
            'term_id.exists'                            => 'The selected term does not belong to your school.',
            'title.unique'                              => 'A timetable with this title already exists in your school.',
            'effective_from.date_format'                => 'Effective from must be a date in YYYY-MM-DD format.',
            'effective_to.date_format'                  => 'Effective to must be a date in YYYY-MM-DD format.',
            'effective_to.after_or_equal'               => 'Effective to date must be on or after the effective from date.',
            'status.in'                                 => 'Status must be one of: draft, active, or archived.',
            'options.working_days.*.integer'            => 'Each working day must be a number (1=Monday, 7=Sunday).',
            'options.working_days.*.min'                => 'Working day values must be between 1 (Monday) and 7 (Sunday).',
            'options.working_days.*.max'                => 'Working day values must be between 1 (Monday) and 7 (Sunday).',
            'day_schedules.*.day_of_week.required_with' => 'Each day schedule entry must specify a day of the week.',
            'day_schedules.*.day_of_week.min'           => 'Day of week must be between 1 (Monday) and 7 (Sunday).',
            'day_schedules.*.day_of_week.max'           => 'Day of week must be between 1 (Monday) and 7 (Sunday).',
            'day_schedules.*.period_schedule_id.exists' => 'The selected period schedule does not exist for your school.',
        ];
    }

    public function attributes(): array
    {
        return [
            'school_section_id'                       => 'school section',
            'term_id'                                 => 'term',
            'effective_from'                          => 'effective from date',
            'effective_to'                            => 'effective to date',
            'day_schedules.*.day_of_week'             => 'day of week',
            'day_schedules.*.period_schedule_id'      => 'period schedule',
            'options.max_periods_per_teacher_per_day' => 'max periods per teacher per day',
            'options.working_days'                    => 'working days',
            'options.distribute_subjects_evenly'      => 'distribute subjects evenly',
        ];
    }
}
