<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SchoolSection;

class UpdateClassLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via permitted()
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
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:class_levels,name,' . $this->classLevel->id . ',id,school_id,' . $school->id . ',school_section_id,' . $this->input('school_section_id'),
            ],
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'school_section_id' => 'required|exists:school_sections,id',
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