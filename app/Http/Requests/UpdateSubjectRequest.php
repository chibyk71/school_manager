<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check(); // Authorization handled in controller via permitted()
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();
        $subjectId = $this->route('subject') ? $this->route('subject')->id : $this->input('id');
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:subjects,code,' . $subjectId . ',id,school_id,' . $school->id,
            ],
            'credit' => 'nullable|numeric|min:0',
            'is_elective' => 'required|boolean',
            'school_section' => 'required|array|min:1',
            'school_section.*' => 'required|exists:school_sections,id',
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