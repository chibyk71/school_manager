<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRoleRequest extends FormRequest
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
            'department_id' => 'sometimes|exists:departments,id',
            'role_id' => 'sometimes|uuid|exists:roles,id',
            'school_section_id' => 'sometimes|nullable|exists:school_sections,id',
            'name' => 'sometimes|string|max:255',
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