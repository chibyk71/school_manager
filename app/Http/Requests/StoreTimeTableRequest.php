<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeTableRequest extends FormRequest
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
            'term_id' => 'required|exists:terms,id',
            'title' => [
                'required',
                'string',
                'max:255',
                'unique:time_tables,title,NULL,id,school_id,' . $school->id . ',term_id,' . $this->input('term_id'),
            ],
            'effective_date' => 'required|date|after_or_equal:today',
            'status' => 'required|in:active,draft,inactive',
            'school_sections' => 'required|array|min:1',
            'school_sections.*' => 'required|exists:school_sections,id',
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