<?php

namespace App\Http\Requests\Student;

use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SubmitApplicationRequest – Validation for Student Application Submission
 *
 * Validates public portal and staff submission paths.
 * Status/reviewer are never client-controlled.
 * Custom fields use the shared HasCustomFields engine (key: custom_fields).
 */
class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'school_section_id' => 'nullable|exists:school_sections,id',
            'class_level_id' => 'nullable|exists:class_levels,id',

            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => ['nullable|string|max:30', new InDynamicEnum('gender', \App\Models\Profile::class)],
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:191',
            'nationality' => 'nullable|string|max:100',
            'state_of_origin' => 'nullable|string|max:100',
            'religion' => ['nullable|string|max:50', new InDynamicEnum('religion', \App\Models\Profile::class)],
            'blood_group' => 'nullable|string|max:10',

            'previous_school' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'previous_school_address' => 'nullable|string|max:500',

            'guardians_data' => 'nullable|array',
            'guardians_data.*.name' => 'required|string|max:150',
            'guardians_data.*.phone' => 'required|string|max:30',
            'guardians_data.*.email' => 'nullable|email|max:191',
            'guardians_data.*.relationship' => 'required|string|max:50',
            'guardians_data.*.is_primary' => 'boolean',

            'documents' => 'nullable|array',
            'documents.*.type' => 'string|max:100',
            'documents.*.path' => 'string',

            'custom_fields' => 'nullable|array',
            'custom_data' => 'nullable|array',

            'source' => ['sometimes', Rule::in(['public_portal', 'admin_direct'])],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Student first name is required.',
            'last_name.required' => 'Student last name is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'academic_session_id.required' => 'Please select the academic session.',
            'guardians_data.*.name.required' => 'Guardian name is required.',
            'guardians_data.*.phone.required' => 'Guardian phone number is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('guardians_data') && ! is_array($this->guardians_data)) {
            $this->merge([
                'guardians_data' => json_decode($this->guardians_data, true) ?? [],
            ]);
        }

        if (! $this->has('source')) {
            $this->merge(['source' => 'public_portal']);
        }
    }

    public function validatedData(): array
    {
        $data = $this->validated();

        if (($data['source'] ?? null) === 'public_portal' && empty($data['submitted_at'])) {
            $data['submitted_at'] = now();
        }

        return $data;
    }
}
