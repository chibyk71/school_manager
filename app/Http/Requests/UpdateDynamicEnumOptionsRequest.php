<?php
/**
 * app/Http/Requests/UpdateDynamicEnumOptionsRequest.php
 *
 * Form request for validating bulk updates to the options array of a fixed DynamicEnum.
 *
 * Features / Problems Solved:
 * - Validates the entire 'options' array submitted from the admin options management modal
 *   (DynamicEnumOptionsForm.vue).
 * - Enforces strict structure:
 *     • At least one option required (prevents empty enums).
 *     • Each option must have a unique, machine-readable 'value' (alpha_dash for safety).
 *     • 'value' must be distinct within the enum (prevents duplicates).
 *     • 'label' is required for display.
 *     • 'color' is optional (Tailwind classes for badges/previews).
 * - Uses Laravel's deep array validation syntax for clean, readable rules.
 * - Authorization: returns true by default – actual tenancy/permission checks are performed
 *   in the controller (DynamicEnumController@updateOptions) for access to the bound DynamicEnum model.
 *   This keeps the request focused on data validation while allowing controller-level policy checks.
 * - Clear, user-friendly error messages (can be overridden in messages() if needed).
 * - Production-ready: secure (prevents malformed/invalid options), performant (single rule set),
 *   and aligned with Laravel best practices.
 * - Works seamlessly with Inertia.js – validation errors automatically returned to frontend.
 *
 * Fits into the DynamicEnums Module:
 * - Used exclusively by DynamicEnumController@updateOptions (PATCH /admin/dynamic-enums/{id}/options).
 * - Ensures only valid, safe option data is persisted to the JSON 'options' column.
 * - Complements the frontend DataTable editing experience (add/edit/delete/reorder).
 * - Enforces data integrity alongside client-side checks in DynamicEnumOptionsForm.vue.
 * - Prevents common issues: duplicate values, empty labels, invalid characters in values.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDynamicEnumOptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Tenancy and permission checks are handled in the controller using the route-model-bound
     * DynamicEnum instance (school_id comparison). This keeps the request lightweight and focused
     * on validation while allowing fine-grained policy logic in the controller/policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The full options array – required and must contain at least one option
            'options'                => ['required', 'array', 'min:1'],

            // Each option's value: required, safe characters, unique within the enum
            'options.*.value'        => ['required', 'alpha_dash', 'max:255', 'distinct:strict'],

            // Each option's label: required for display
            'options.*.label'        => ['required', 'string', 'max:255'],

            // Optional color class (Tailwind badge styling)
            'options.*.color'        => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * Optional – provides clearer feedback in the admin modal.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.required'              => 'At least one option is required.',
            'options.min'                   => 'At least one option is required.',
            'options.*.value.required'      => 'Each option must have a value.',
            'options.*.value.alpha_dash'    => 'Option values may only contain letters, numbers, dashes and underscores.',
            'options.*.value.distinct'      => 'Option values must be unique.',
            'options.*.label.required'      => 'Each option must have a display label.',
        ];
    }
}
