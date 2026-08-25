<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\PeriodSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StorePeriodScheduleRequest
 *
 * Validates the creation (or full replacement on PUT) of a PeriodSchedule
 * together with its nested array of ClassPeriod rows.
 *
 * ── Request Shape ─────────────────────────────────────────────────────────────
 * {
 *   "name":              "Regular Day",
 *   "description":       "Standard Monday–Thursday schedule",
 *   "school_start_time": "08:00",
 *   "color":             "#3B82F6",
 *   "sort_order":        1,
 *   "is_active":         true,
 *   "periods": [
 *     { "name": "Period 1",    "order": 1,  "duration_minutes": 40, "is_break": false },
 *     { "name": "Period 2",    "order": 2,  "duration_minutes": 40, "is_break": false },
 *     { "name": "Period 3",    "order": 3,  "duration_minutes": 40, "is_break": false },
 *     { "name": "Short Break", "order": 4,  "duration_minutes": 20, "is_break": true  },
 *     { "name": "Period 4",    "order": 5,  "duration_minutes": 40, "is_break": false },
 *     ...
 *   ]
 * }
 *
 * ── Key Validation Decisions ──────────────────────────────────────────────────
 * - `periods` must contain at least one lesson period (is_break = false). A
 *   schedule consisting entirely of breaks has no assignable slots and would
 *   confuse the generator.
 *
 * - `periods.*.order` must be unique within the submitted array. Enforced via
 *   the custom `withValidator` hook (not expressible as a built-in rule).
 *
 * - `school_start_time` is validated as HH:MM format (the DB stores time as
 *   string). We intentionally do NOT validate as 'date_format:H:i:s' because
 *   frontend inputs produce "HH:MM" without seconds.
 *
 * - `color` is validated as a 6-digit hex code with a leading #.
 *
 * - On update: the `unique` rule for `name` excludes the current schedule's ID
 *   via `ignore()`. The route must bind {periodSchedule} for this to work.
 *
 * ── Usage ─────────────────────────────────────────────────────────────────────
 * Route::post('period-schedules', [PeriodScheduleController::class, 'store'])
 *      ->middleware('auth');
 * Route::put('period-schedules/{periodSchedule}', [PeriodScheduleController::class, 'update'])
 *      ->middleware('auth');
 */
class StorePeriodScheduleRequest extends FormRequest
{
    /**
     * Authorization is handled by PeriodSchedulePolicy.
     * The controller calls $this->authorize('create', PeriodSchedule::class)
     * or $this->authorize('update', $schedule) before dispatching.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        // On update the route model binding provides the existing schedule.
        $scheduleId = optional($this->route('periodSchedule'))->id;

        $schoolId = auth()->user()?->school_id;

        return [
            // ── Schedule Header ───────────────────────────────────────────────

            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('period_schedules', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($scheduleId),
                // Prevents two "Regular Day" schedules in the same school,
                // but allows the same name across different schools.
            ],

            'description' => [
                'nullable',
                'string',
                'max:255',
            ],

            'school_start_time' => [
                'nullable',
                'string',
                'regex:/^([01]\d|2[0-3]):[0-5]\d$/',
                // Accepts HH:MM (24-hour). DB stores as time column.
                // Example valid values: "07:30", "08:00", "13:45"
            ],

            'color' => [
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                // 6-digit hex only. Example: "#3B82F6", "#F59E0B"
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],

            'is_active' => [
                'boolean',
            ],

            // ── Nested Periods Array ───────────────────────────────────────────

            'periods' => [
                'required',
                'array',
                'min:1',
                // Must contain at least one period. The withValidator hook
                // enforces that at least one of them is a lesson (not a break).
            ],

            'periods.*.name' => [
                'required',
                'string',
                'max:100',
                // No unique rule here — uniqueness within the schedule is
                // enforced by the DB unique constraint and validated in
                // withValidator() below (array-level distinctness check).
            ],

            'periods.*.order' => [
                'required',
                'integer',
                'min:1',
                'max:255',
                // Uniqueness within the submitted array is checked in withValidator().
            ],

            'periods.*.duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:480',
                // Min 1 min (a zero-duration period is meaningless).
                // Max 480 min (8 hours) — handles the most extreme school days.
            ],

            'periods.*.is_break' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Cross-field validation that cannot be expressed as field-level rules.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            $periods = $this->input('periods', []);

            if (! is_array($periods)) {
                return;
            }

            // ── Rule: at least one lesson period (not all breaks) ─────────────
            $hasLesson = collect($periods)->contains(
                fn ($p) => isset($p['is_break']) && ! filter_var($p['is_break'], FILTER_VALIDATE_BOOLEAN)
            );

            if (! $hasLesson) {
                $v->errors()->add(
                    'periods',
                    'The schedule must contain at least one lesson period (not just breaks).'
                );
            }

            // ── Rule: period orders must be unique within the submitted array ──
            $orders = array_column($periods, 'order');
            $validOrders = array_filter($orders, fn ($o) => is_numeric($o));

            if (count($validOrders) !== count(array_unique($validOrders))) {
                $v->errors()->add(
                    'periods',
                    'Each period must have a unique order number within the schedule.'
                );
            }

            // ── Rule: period names must be unique within the submitted array ───
            $names = array_filter(array_column($periods, 'name'));
            $lowerNames = array_map('strtolower', $names);

            if (count($lowerNames) !== count(array_unique($lowerNames))) {
                $v->errors()->add(
                    'periods',
                    'Each period must have a unique name within the schedule.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.unique'                        => 'A schedule with this name already exists for your school.',
            'school_start_time.regex'            => 'Start time must be in HH:MM format (e.g. 08:00).',
            'color.regex'                        => 'Color must be a 6-digit hex code (e.g. #3B82F6).',
            'periods.required'                   => 'A schedule must have at least one period.',
            'periods.min'                        => 'A schedule must have at least one period.',
            'periods.*.name.required'            => 'Each period must have a name.',
            'periods.*.order.required'           => 'Each period must have an order number.',
            'periods.*.order.min'                => 'Period order must be at least 1.',
            'periods.*.order.max'                => 'Period order cannot exceed 255.',
            'periods.*.duration_minutes.required' => 'Each period must have a duration.',
            'periods.*.duration_minutes.min'     => 'A period duration must be at least 1 minute.',
            'periods.*.duration_minutes.max'     => 'A period duration cannot exceed 480 minutes.',
            'periods.*.is_break.required'        => 'Each period must specify whether it is a break.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                          => 'schedule name',
            'school_start_time'             => 'school start time',
            'sort_order'                    => 'sort order',
            'is_active'                     => 'active status',
            'periods.*.name'                => 'period name',
            'periods.*.order'               => 'period order',
            'periods.*.duration_minutes'    => 'period duration',
            'periods.*.is_break'            => 'break flag',
        ];
    }
}
