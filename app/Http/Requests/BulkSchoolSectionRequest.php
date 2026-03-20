<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * BulkSchoolSectionRequest — Production-Ready
 *
 * Validates all bulk operations on SchoolSection records in a single request
 * class. The 'action' field determines which operation is being performed,
 * which drives both conditional validation rules and permission checks.
 *
 * ── Supported Actions ────────────────────────────────────────────────────
 * toggle  → activate or deactivate multiple sections (requires is_active)
 * delete  → soft-delete multiple sections
 * restore → restore multiple soft-deleted sections
 *
 * ── Authorization ────────────────────────────────────────────────────────
 * Permission check is driven by the action field:
 *   toggle  → sections.update
 *   delete  → sections.delete
 *   restore → sections.restore
 *
 * Authorization is checked in this Form Request (not Controller+Policy)
 * because the permission varies per action and needs the action value
 * to resolve correctly. The Controller still calls Policy for instance-level
 * checks on individual operations when needed.
 *
 * ── IDs Array Security ───────────────────────────────────────────────────
 * Four attack vectors are mitigated:
 *
 * 1. Cross-tenant IDs: BelongsToSchool global scope on SchoolSection
 *    ensures whereIn() only matches records in the current school.
 *    IDs from other schools are silently ignored by the query.
 *
 * 2. DoS via oversized array: capped at 250 IDs — the maximum DataTable
 *    page size. Prevents memory exhaustion on large bulk requests.
 *
 * 3. Invalid format: each ID validated as uuid to prevent injection
 *    of unexpected values into the whereIn() clause.
 *
 * 4. Empty array: min:1 ensures at least one ID is present. An empty
 *    bulk operation would silently succeed with 0 affected rows,
 *    which is misleading and wasteful.
 *
 * ── No DB Existence Check ────────────────────────────────────────────────
 * IDs are NOT individually validated against the database here.
 * Reasons:
 *   - Would fire up to 250 individual existence queries before the bulk
 *     operation runs (1 per ID) — severe performance problem
 *   - Service layer runs one whereIn() query naturally scoped by
 *     BelongsToSchool — non-existent IDs are silently ignored
 *   - Service returns affected row count — controller uses this to
 *     detect partial matches and respond accordingly
 *
 * ── Conditional is_active ────────────────────────────────────────────────
 * is_active is only required when action = 'toggle'. For delete and
 * restore, sending is_active is allowed but ignored by the service.
 * This permissive approach avoids frontend having to strip fields
 * before submission.
 *
 * ── Affected Count Response Pattern ─────────────────────────────────────
 * The service returns the count of actually affected records. This allows
 * the controller to detect when submitted IDs didn't all match:
 *   submitted: 5 IDs, affected: 3 → 2 IDs were invalid/already in state
 * The controller can include this in the response for frontend feedback.
 *
 * @see App\Services\SchoolSectionService   (executes bulk operations)
 * @see App\Policies\SchoolSectionPolicy    (instance-level authorization)
 * @see App\Http\Controllers\Settings\SchoolSectionController
 */
class BulkSchoolSectionRequest extends FormRequest
{
    /**
     * Authorize based on which bulk action is being performed.
     *
     * Maps each action to its corresponding permission:
     *   toggle  → sections.update (changing active state is an update)
     *   delete  → sections.delete
     *   restore → sections.restore
     *
     * Permission-based, not role-based. Which roles carry these permissions
     * is defined per-tenant — never hardcoded here.
     *
     * Note: 'action' may not be validated yet when authorize() runs
     * (authorize runs before rules). We use a safe fallback of false
     * for any unrecognized action to fail closed.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $action = $this->input('action');

        if ($user === null) {
            return false;
        }

        return match ($action) {
            'toggle' => $user->hasPermission('sections.update'),
            'delete' => $user->hasPermission('sections.delete'),
            'restore' => $user->hasPermission('sections.restore'),
            // Unknown action — fail closed, rules() will catch the format error
            default => false,
        };
    }

    /**
     * Validation rules for bulk operations.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ── action ────────────────────────────────────────────────
            // Determines which operation runs and which permission is
            // required. Must be present and one of the three valid values.
            'action' => [
                'required',
                'string',
                Rule::in(['toggle', 'delete', 'restore']),
            ],

            // ── ids ───────────────────────────────────────────────────
            // Array of SchoolSection UUIDs to operate on.
            // min:1 prevents empty bulk operations.
            // max:250 caps at full DataTable page size to prevent DoS.
            'ids' => [
                'required',
                'array',
                'min:1',
                'max:250',
            ],

            // Each element must be a valid UUID format.
            // BelongsToSchool scope handles cross-tenant safety at query level.
            'ids.*' => [
                'required',
                'uuid',
            ],

            // ── is_active ─────────────────────────────────────────────
            // Required only when action = 'toggle'.
            // For delete/restore: nullable and ignored by service if present.
            // Uses required_if to avoid frontend needing to strip the field
            // for non-toggle operations.
            'is_active' => [
                Rule::requiredIf(fn() => $this->input('action') === 'toggle'),
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Human-readable attribute names for error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => 'bulk action',
            'ids' => 'selected sections',
            'ids.*' => 'section ID',
            'is_active' => 'active status',
        ];
    }

    /**
     * Custom messages for specific rule failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' =>
                'A bulk action must be specified.',

            'action.in' =>
                'Invalid bulk action. Allowed actions are: toggle, delete, restore.',

            'ids.required' =>
                'No sections were selected for this operation.',

            'ids.min' =>
                'At least one section must be selected.',

            'ids.max' =>
                'A maximum of 250 sections can be processed in a single bulk operation.',

            'ids.*.uuid' =>
                'One or more selected section IDs are invalid.',

            'is_active.required_if' =>
                'An active status (true or false) is required for the toggle action.',

            'is_active.boolean' =>
                'Active status must be true or false.',
        ];
    }
}
