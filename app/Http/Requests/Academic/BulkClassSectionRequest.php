<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BulkClassSectionRequest — validates all bulk state-change operations.
 *
 * ── Operations Covered ────────────────────────────────────────────────────────
 * One request class handles all bulk operations because the payload shape
 * is identical (an array of IDs + an action discriminator). The controller
 * routes to the correct service method based on the action value or the
 * specific route endpoint.
 *
 * Actions:
 *   'delete'        → ClassSectionService::bulkDelete()
 *   'restore'       → ClassSectionService::bulkRestore()
 *   'force-delete'  → ClassSectionService::bulkForceDelete()
 *   'toggle'        → ClassSectionService::bulkToggleStatus()   (requires is_active)
 *   'reorder'       → ClassSectionService::reorder()            (ids = ordered array)
 *
 * ── Reorder Special Case ──────────────────────────────────────────────────────
 * For reorder, `ids` is the ordered array of ALL sections being reordered
 * (not a subset to delete). The service assigns sort_order = (position+1)*10.
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Authorization is handled per-action in the controller since each action
 * maps to a different policy method.
 */
class BulkClassSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The action discriminator — determines which service method is called
            'action' => [
                'required',
                'string',
                Rule::in(['delete', 'restore', 'force-delete', 'toggle', 'reorder']),
            ],

            // Array of section UUIDs — required for all actions
            'ids' => [
                'required',
                'array',
                'min:1',
                'max:200', // Sanity cap — more than enough for any school
            ],

            'ids.*' => [
                'required',
                'uuid',
            ],

            // Required only when action = 'toggle'
            'is_active' => [
                'required_if:action,toggle',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required'    => 'An action must be specified.',
            'action.in'          => 'Invalid action. Must be one of: delete, restore, force-delete, toggle, reorder.',
            'ids.required'       => 'At least one section must be selected.',
            'ids.min'            => 'At least one section must be selected.',
            'ids.*.uuid'         => 'One or more selected section IDs are invalid.',
            'is_active.required_if' => 'Active state is required when toggling status.',
            'is_active.boolean'  => 'Active state must be true or false.',
        ];
    }
}
