<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Request validation for updating a staff member.
 *
 * @package App\Http\Requests
 */
class UpdateStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update-staff');
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
            'user_id' => 'sometimes|uuid|exists:users,id',
            'department_role_id' => 'sometimes|nullable|exists:department_roles,id',
            'custom_fields' => 'nullable|array',
            'custom_fields.*' => 'nullable|string|max:255',
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'exists:school_sections,id,school_id,' . $school->id,
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
            'department_role_id' => 'department role',
            'custom_fields' => 'custom fields',
            'section_ids' => 'school sections',
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
            Log::error('No active school found during staff update.');
            throw ValidationException::withMessages(['school' => 'No active school found.']);
        }
        return $school;
    }
}