<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Request validation for creating a student.
 *
 * @package App\Http\Requests
 */
class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create-students');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = $this->getActiveSchool();

        return [
            'user_id' => 'required|uuid|exists:users,id',
            'school_section_id' => 'required|exists:school_sections,id,school_id,' . $school->id,
            'custom_fields' => 'nullable|array',
            'custom_fields.*' => 'nullable|string|max:255',
            'guardian_ids' => 'nullable|array',
            'guardian_ids.*' => 'uuid|exists:guardians,id,school_id,' . $school->id,
            'class_section_ids' => 'nullable|array',
            'class_section_ids.*' => 'exists:class_sections,id,school_id,' . $school->id,
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'school_section_id' => 'school section',
            'custom_fields' => 'custom fields',
            'guardian_ids' => 'guardians',
            'class_section_ids' => 'class sections',
        ];
    }

    /**
     * Get the active school model.
     *
     * @return \App\Models\School
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();
        if (!$school) {
            Log::error('No active school found during student creation.');
            throw ValidationException::withMessages(['school' => 'No active school found.']);
        }
        return $school;
    }
}