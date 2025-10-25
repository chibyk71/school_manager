<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeTableRequest extends FormRequest
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
        $timetableId = $this->route('timetable') ? $this->route('timetable')->id : $this->input('id');
        return [
            'term_id' => 'sometimes|exists:terms,id',
            'title' => [
                'sometimes',
                'string',
                'max:255',
                'unique:time_tables,title,' . $timetableId . ',id,school_id,' . $school->id . ',term_id,' . ($this->input('term_id') ?? $this->route('timetable')?->term_id),
            ],
            'effective_date' => 'sometimes|date|after_or_equal:today',
            'status' => 'sometimes|in:active,draft,inactive',
            'school_sections' => 'sometimes|array|min:1',
            'school_sections.*' => 'sometimes|exists:school_sections,id',
            'options' => 'nullable|array',
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