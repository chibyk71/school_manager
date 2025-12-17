<?php

namespace App\Http\Requests;

use App\Support\DepartmentCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateDepartmentRequest – Form Request for Updating an Existing Department
 *
 * Features Implemented & Problems Solved (Production-Ready – December 2025):
 *
 * 1. Centralized validation for department updates:
 *    - Keeps controller thin and reusable.
 *    - Ensures consistent rules across API and Inertia flows.
 *
 * 2. Multi-tenant safety:
 *    - Automatically injects current school_id in prepareForValidation().
 *    - Name uniqueness enforced per school, ignoring the current department's ID.
 *
 * 3. Partial update support:
 *    - All core fields use 'sometimes' – only validates fields that are present.
 *    - Allows updating just one field (e.g., description only) without requiring others.
 *
 * 4. Full support for advanced role + section scoping payload:
 *    - Validates optional 'roles' array with nested role_id and section_ids.
 *    - section_ids are optional (empty array = department-wide role).
 *    - Ensures role and section IDs exist in database.
 *
 * 5. Industry-standard practices:
 *    - Uses FormRequest for separation of concerns.
 *    - Rule::unique() with ignore() for clean, maintainable uniqueness check.
 *    - Nested array validation for complex role/section structure.
 *    - Authorization delegated to policy (best practice).
 *
 * 6. Frontend Integration Notes:
 *    - Used by edit modal (useModalForm composable).
 *    - Payload example (partial update):
 *      {
 *        "description": "Updated description",
 *        "roles": [
 *          { "role_id": "uuid-1", "section_ids": [1] },
 *          { "role_id": "uuid-2", "section_ids": [] }
 *        ]
 *      }
 *    - Validation errors returned as JSON → displayed in PrimeVue form fields.
 *    - Works seamlessly with update() controller method that handles section syncing.
 *
 * 7. Scalability:
 *    - Minimal overhead.
 *    - Ready for large schools with many departments, roles, and sections.
 */
class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled in controller via policy (Gate::authorize('update', $department)).
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the input data before validation.
     *
     * Injects the current school_id if not provided – essential for multi-tenant isolation
     * and uniqueness checks.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();

        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();
        $departmentId = $this->route('department')?->id ?? $this->input('id');

        return [
            // Core department fields – all optional ('sometimes') for partial updates
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where('school_id', $school?->id)
                    ->ignore($departmentId),
            ],
            'category' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', DepartmentCategories::getKeys()),
            ],
            'description' => 'sometimes|nullable|string',
            'effective_date' => 'sometimes|nullable|date|after_or_equal:today',

            // Role assignments with optional section scoping – fully optional on update
            'roles' => 'sometimes|required|array|min:1',
            'roles.*.role_id' => 'required|uuid|exists:roles,id',
            'roles.*.section_ids' => 'sometimes|array',
            'roles.*.section_ids.*' => 'uuid|exists:school_sections,id',
        ];
    }

    /**
     * Custom user-friendly error messages.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A department with this name already exists in your school.',
            'category.in' => 'The selected category is invalid.',
            'roles.required' => 'At least one role must be assigned if updating roles.',
            'roles.*.role_id.exists' => 'One or more selected roles do not exist.',
            'roles.*.section_ids.*.exists' => 'One or more selected sections do not exist.',
        ];
    }
}
