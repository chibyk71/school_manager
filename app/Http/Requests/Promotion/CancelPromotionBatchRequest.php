<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * CancelPromotionBatchRequest
 *
 * Form request for cancelling a promotion batch.
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Validates cancellation of a promotion batch before it reaches terminal state.
 *
 * Key Features:
 * - Authorization based on 'promotions.cancel' permission
 * - Only allows cancellation of non-terminal batches (draft, pending, reviewing, approved)
 * - Requires a cancellation_reason (mandatory for audit trail)
 * - Prevents cancellation of already completed or cancelled batches
 *
 * Fits into the Promotion Module:
 * - Used by PromotionBatchController@cancel
 * - Updates batch status to 'cancelled' and stores reason in metadata
 * - Triggers PromotionBatchCancelled event
 * - Soft-deletes the batch (as per migration design)
 */

class CancelPromotionBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to cancel this batch.
     */
    public function authorize(): bool
    {
        $batch = $this->route('promotion_batch');

        if (!$batch instanceof PromotionBatch) {
            return false;
        }

        if (!$this->user()->can('promotions.cancel')) {
            return false;
        }

        // Cannot cancel terminal states
        return !in_array($batch->status, ['completed', 'cancelled'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => 'required|string|min:10|max:1000',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Please provide a reason for cancelling this promotion batch.',
            'cancellation_reason.min'      => 'Cancellation reason must be at least 10 characters long.',
            'cancellation_reason.max'      => 'Cancellation reason cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get the PromotionBatch instance from the route.
     */
    public function promotionBatch(): PromotionBatch
    {
        return $this->route('promotion_batch');
    }
}