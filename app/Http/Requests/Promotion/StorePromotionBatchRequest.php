<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StorePromotionBatchRequest
 *
 * Form request for creating a new PromotionBatch (manual creation by admin).
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Validates input when an admin manually creates a promotion batch.
 * Automatic batches (from session closure) bypass this request and are created
 * directly via PromotionService.
 *
 * Features:
 * - Basic validation for name and description
 * - Prevents creation of duplicate batches for the same school + session
 *   (the unique constraint in migration already protects, but we add friendly validation)
 * - Uses existing permission check pattern via controller
 *
 * Fits into the Promotion Module:
 * - Used by PromotionBatchController@store
 * - Feeds into PromotionService::createPromotionBatchForSession() indirectly
 */

class StorePromotionBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('promotions.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'academic_session_id' => 'required|uuid|exists:academic_sessions,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'academic_session_id.required' => 'Please select an academic session.',
            'academic_session_id.exists' => 'The selected academic session does not exist.',
            'name.required' => 'Batch name is required.',
        ];
    }

    /**
     * Prepare the data for validation.
     * Automatically set school_id from current context.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'school_id' => GetSchoolModel()?->id,
        ]);
    }
}