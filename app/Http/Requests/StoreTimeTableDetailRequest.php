<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeTableDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check(); // Authorization handled in controller via policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();
        return [
            'timetable_id' => 'required|exists:time_tables,id',
            'class_period_id' => 'required|exists:class_periods,id',
            'teacher_class_section_subject_id' => 'required|exists:teacher_class_section_subjects,id',
            'day' => [
                'required',
                'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                // Implement a custom validation rule or model observer to prevent overlapping slots for the same teacher, class section, or classroom on the same day and time:
                Rule::unique('time_table_details')
                    ->where(function ($query) use ($school) {
                        return $query->where('school_id', $school->id)
                            ->where('timetable_id', $this->input('timetable_id'))
                            ->where('class_period_id', $this->input('class_period_id'))
                            ->where('start_time', $this->input('start_time'));
                    }),
            ],
            'start_time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'end_time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/|after:start_time',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();
        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
    }
}