<?php

namespace App\Http\Requests\BulkActions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BulkActionRequest.php
 *
 * Reusable Form Request for all bulk operations across the application.
 *
 * This is the single entry point for bulk actions coming from the frontend (especially from
 * your useDeleteResource and useRestoreResource composables). It centralizes validation,
 * sanitization, and helper methods so that controllers and the BulkActionService receive
 * clean, consistent, and validated data.
 *
 * Features / Problems Solved:
 * - Centralized validation for `ids[]` array and optional `action` + `force` parameters
 * - Strict type checking and minimum count enforcement
 * - Helper methods (`getIds()`, `isForceDelete()`, `getAction()`, `getCount()`, `isBulk()`)
 *   that make controller code extremely clean
 * - Clear, user-friendly validation messages that match your PrimeVue toast style
 * - Supports both dedicated endpoints (destroy, restore) and a single `bulkAction` endpoint
 * - Fully compatible with your existing frontend composables that send `{ ids: [...], force?: bool }`
 * - Production-ready security: prevents empty arrays, non-integer IDs, invalid actions
 *
 * Role in the Bulk Actions Package:
 * - Injected into controllers and passed down to BulkActionService
 * - Used by every BulkActionHandler via the `handle()` method
 * - Works seamlessly with BulkActionRegistry and BulkActionService
 * - Acts as the contract between frontend and backend for bulk operations
 *
 * How it fits into the architecture:
 * 1. Frontend (useDeleteResource / useRestoreResource) → sends POST with ids
 * 2. Laravel routes → inject BulkActionRequest
 * 3. Request validates data
 * 4. BulkActionService receives validated request + model class
 * 5. Registry picks correct handler → handler uses request helpers
 *
 * Extensibility:
 * New fields (e.g. `reason`, `notify`) can be added easily without breaking existing actions.
 */

class BulkActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * You can override this in child requests or add middleware for resource-specific
     * permission checks (e.g. 'bulk-delete-students').
     */
    public function authorize(): bool
    {
        return true; // Extend with policies or middleware as needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'integer',
                'min:1',
                // You can add exists rules per resource if needed in child requests
                // Rule::exists('students', 'id'),
            ],

            // Optional action parameter (useful for unified bulkAction endpoint)
            'action' => [
                'nullable',
                'string',
                Rule::in([
                    'delete',
                    'restore',
                    'force_delete',
                    // Future actions can be added here:
                    // 'activate', 'deactivate', 'approve', 'export', etc.
                ]),
            ],

            // Force delete flag (used mainly with delete action)
            'force' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom validation error messages.
     *
     * These messages are designed to be clear and match your frontend toast style.
     */
    public function messages(): array
    {
        return [
            'ids.required'    => 'Please select at least one record to perform this action.',
            'ids.array'       => 'The selected records must be provided as an array.',
            'ids.min'         => 'Please select at least one record.',
            'ids.*.integer'   => 'Each selected ID must be a valid integer.',
            'ids.*.min'       => 'Each selected ID must be greater than zero.',
            'action.in'       => 'The specified bulk action is not supported.',
        ];
    }

    /**
     * Get the validated list of IDs as clean integers.
     *
     * @return array<int>
     */
    public function getIds(): array
    {
        return array_map('intval', $this->validated('ids', []));
    }

    /**
     * Determine if this request should perform a force (permanent) delete.
     *
     * @return bool
     */
    public function isForceDelete(): bool
    {
        return $this->boolean('force')
            || $this->validated('action') === 'force_delete';
    }

    /**
     * Get the requested bulk action name.
     *
     * Falls back to 'delete' if no action is provided (for backward compatibility with
     * dedicated destroy endpoints).
     */
    public function getAction(): string
    {
        return $this->validated('action') ?? 'delete';
    }

    /**
     * Get the count of records being acted upon.
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->getIds());
    }

    /**
     * Check if this is a bulk operation (more than one record).
     *
     * @return bool
     */
    public function isBulk(): bool
    {
        return $this->getCount() > 1;
    }

    /**
     * Get a human-readable description of the operation for logging or messages.
     */
    public function getDescription(): string
    {
        $action = $this->getAction();
        $count = $this->getCount();
        $recordWord = $count === 1 ? 'record' : 'records';

        return match ($action) {
            'restore'      => "Restoring {$count} {$recordWord}",
            'force_delete' => "Permanently deleting {$count} {$recordWord}",
            default        => "Deleting {$count} {$recordWord}",
        };
    }
}
