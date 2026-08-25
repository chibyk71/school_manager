<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ApprovePromotionBatchRequest
 *
 * Form request for approving a promotion batch (moving it from pending/reviewing → approved).
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Validates the approval action and optionally captures approval comments.
 *
 * Key Features:
 * - Authorization based on 'promotions.approve' permission + batch status
 * - Optional approval_comments field (useful for audit trail)
 * - Prevents approval of already approved, executing, completed or cancelled batches
 *
 * Fits into the Promotion Module:
 * - Used by PromotionBatchController@approve
 * - Triggers PromotionBatchApproved event after successful approval
 * - Approval_comments are stored on the PromotionBatch model
 */

class ApprovePromotionBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to approve this batch.
     */
    public function authorize(): bool
    {
        $batch = $this->route('promotion_batch');

        if (!$batch instanceof PromotionBatch) {
            return false;
        }

        if (!$this->user()->can('promotions.approve')) {
            return false;
        }

        // Only batches in pending or reviewing state can be approved
        return in_array($batch->status, ['pending', 'reviewing'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'approval_comments' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'approval_comments.max' => 'Approval comments cannot exceed 1000 characters.',
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