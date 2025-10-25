<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRoleRequest extends FormRequest
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
            'department_id' => 'required|exists:departments,id',
            'role_id' => 'required|uuid|exists:roles,id',
            'school_section_id' => 'nullable|exists:school_sections,id',
            'name' => 'required|string|max:255',
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