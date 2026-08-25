<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ExecutePromotionBatchRequest
 *
 * Form request for executing an approved promotion batch.
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Validates the execution action before dispatching the ProcessStudentPromotion job.
 *
 * Key Features:
 * - Strict authorization: only users with 'promotions.execute' permission
 * - Only batches in 'approved' status can be executed
 * - Optional remarks that will be stored on the batch and later on PromotionHistory records
 * - Prevents double execution of the same batch
 *
 * Fits into the Promotion Module:
 * - Used by PromotionBatchController@execute
 * - Triggers the queued ProcessStudentPromotion job
 * - Final step before permanent PromotionHistory records are written
 */

class ExecutePromotionBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to execute this batch.
     */
    public function authorize(): bool
    {
        $batch = $this->route('promotion_batch');

        if (!$batch instanceof PromotionBatch) {
            return false;
        }

        if (!$this->user()->can('promotions.execute')) {
            return false;
        }

        // Only approved batches can be executed
        return $batch->status === 'approved';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'remarks.max' => 'Remarks cannot exceed 1000 characters.',
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