<?php

namespace App\Http\Requests;

use App\Support\DepartmentCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreDepartmentRequest – Form Request for Creating a New Department
 *
 * Features Implemented & Problems Solved (Production-Ready – December 2025):
 *
 * 1. Centralized validation for department creation:
 *    - Keeps controller thin and reusable.
 *    - Ensures consistent rules across API and Inertia flows.
 *
 * 2. Multi-tenant safety:
 *    - Automatically injects current school_id in prepareForValidation().
 *    - Name uniqueness enforced per school (prevents cross-school conflicts).
 *
 * 3. Robust & user-friendly rules:
 *    - name: required, string, max 255, unique within school.
 *    - category: required and restricted to allowed enum values from DepartmentCategories.
 *    - description: optional full text.
 *    - effective_date: optional date, must be today or future (useful for planned departments).
 *
 * 4. Supports advanced frontend payload (role + section scoping):
 *    - Allows 'roles' array with nested role_id and section_ids.
 *    - Validates existence of roles and sections.
 *    - section_ids are optional (empty array = department-wide role).
 *
 * 5. Industry-standard practices:
 *    - Uses FormRequest for separation of concerns.
 *    - Rule::unique() with dynamic school_id for clarity and maintainability.
 *    - Proper nesting validation for complex role/section structure.
 *    - Authorization delegated to policy (best practice).
 *
 * 6. Frontend Integration Notes:
 *    - Used by create modal (useModalForm composable).
 *    - Payload example:
 *      {
 *        "name": "Science",
 *        "category": "academic",
 *        "description": "...",
 *        "effective_date": "2026-01-01",
 *        "roles": [
 *          { "role_id": "uuid-1", "section_ids": [1, 3] },
 *          { "role_id": "uuid-2", "section_ids": [] }
 *        ]
 *      }
 *    - Validation errors returned as JSON → displayed in PrimeVue form.
 *
 * 7. Scalability:
 *    - Minimal overhead.
 *    - Ready for large schools with many departments/roles/sections.
 */
class StoreDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled in controller via policy (Gate::authorize('create', Department::class)).
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the input data before validation.
     *
     * Injects the current school_id if not provided – essential for multi-tenant isolation.
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

        return [
            // Core department fields
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where('school_id', $school?->id)
                    ->ignore(null), // NULL id = create scenario
            ],
            'category' => [
                'required',
                'string',
                'in:' . implode(',', DepartmentCategories::getKeys()),
            ],
            'description' => 'nullable|string',
            'effective_date' => 'nullable|date|after_or_equal:today',

            // Role assignments with optional section scoping
            'roles' => 'required|array|min:1',
            'roles.*.role_id' => 'required|uuid|exists:roles,id',
            'roles.*.section_ids' => 'sometimes|array',
            'roles.*.section_ids.*' => 'uuid|exists:school_sections,id',
        ];
    }

    /**
     * Custom error messages for better UX (optional – can be moved to lang/validation.php).
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A department with this name already exists in your school.',
            'category.in' => 'The selected category is invalid.',
            'roles.required' => 'At least one role must be assigned to the department.',
            'roles.*.role_id.exists' => 'One or more selected roles do not exist.',
            'roles.*.section_ids.*.exists' => 'One or more selected sections do not exist.',
        ];
    }
}
