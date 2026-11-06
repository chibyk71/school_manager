<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Validation for creating a staff member (hire flow).
 *
 * Person identity fields belong to Profile. Authentication Account creation
 * is optional and delegated to UserAccountService by StaffHiringService.
 * Does not accept an existing user_id for identity matching — hiring creates
 * a new Person (or, in future phases, may attach to an explicit person_id).
 */
class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-staff') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = $this->getActiveSchool();

        return [
            // Person (Profile) identity — owned by Profile, not User
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',

            // Employment
            'department_role_id' => 'nullable|exists:department_roles,id',
            'department_id' => 'nullable|uuid',
            'section_id' => 'nullable|uuid',
            'date_of_employment' => 'nullable|date',
            'employment_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',

            // Account provisioning (optional)
            'create_login' => 'sometimes|boolean',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'password_confirmation' => 'nullable|string',

            // Roles (authorization domain; not assigned by UserAccountService)
            'roles' => 'nullable|array',
            'roles.*' => 'string',

            // Extras
            'custom_fields' => 'nullable|array',
            'custom_fields.*' => 'nullable|string|max:255',
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'exists:school_sections,id,school_id,'.$school->id,
        ];
    }

    /**
     * Normalize frontend field aliases (f_name / l_name / confirmpassword) into
     * the canonical service contract before validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->filled('f_name') && ! $this->filled('first_name')) {
            $merge['first_name'] = $this->input('f_name');
        }
        if ($this->filled('m_name') && ! $this->filled('middle_name')) {
            $merge['middle_name'] = $this->input('m_name');
        }
        if ($this->filled('l_name') && ! $this->filled('last_name')) {
            $merge['last_name'] = $this->input('l_name');
        }
        if ($this->filled('confirmpassword') && ! $this->filled('password_confirmation')) {
            $merge['password_confirmation'] = $this->input('confirmpassword');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'department_role_id' => 'department role',
            'section_ids' => 'school sections',
        ];
    }

    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();
        if (! $school) {
            Log::error('No active school found during staff creation.');
            throw ValidationException::withMessages(['school' => 'No active school found.']);
        }

        return $school;
    }
}
