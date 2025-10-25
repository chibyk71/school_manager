<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassSectionRequest extends FormRequest
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
            'class_level_id' => 'required|exists:class_levels,id',
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:class_sections,name,NULL,id,school_id,' . $school->id . ',class_level_id,' . $this->input('class_level_id'),
            ],
            'room' => 'nullable|string|max:255|unique:class_sections,room,NULL,id,school_id,' . $school->id,
            'capacity' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
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