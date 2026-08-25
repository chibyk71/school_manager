<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Promotion\PromotionStudent;

/**
 * OverridePromotionStudentRequest
 *
 * Form request for human override of a student's promotion recommendation
 * during the review phase.
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Validates when an authorized user (with promotions.review permission)
 * changes a student's final_decision during batch review.
 *
 * Key Rules:
 * - final_decision must be one of: promote, repeat, graduate
 * - override_reason is required when changing the recommendation
 * - Only allowed on non-processed students in pending/reviewing batches
 *
 * Fits into the Promotion Module:
 * - Used by PromotionBatchController (or dedicated OverrideController)
 * - Directly updates PromotionStudent::final_decision + override metadata
 * - Triggers StudentDecisionOverridden event
 */

class OverridePromotionStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $promotionStudent = $this->route('promotion_student');

        if (!$promotionStudent instanceof PromotionStudent) {
            return false;
        }

        // Must have review permission
        if (!$this->user()->can('promotions.review')) {
            return false;
        }

        // Cannot override already processed students
        if ($promotionStudent->isProcessed()) {
            return false;
        }

        // Batch must still be in reviewable state
        return in_array($promotionStudent->promotionBatch->status, ['pending', 'reviewing'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'final_decision' => 'required|in:promote,repeat,graduate',
            'override_reason' => 'required|string|min:10|max:1000',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'final_decision.required' => 'Please select a final decision (Promote, Repeat, or Graduate).',
            'final_decision.in' => 'Invalid decision selected.',
            'override_reason.required' => 'Override reason is required when changing the system recommendation.',
            'override_reason.min' => 'Override reason must be at least 10 characters long.',
        ];
    }

    /**
     * Get the PromotionStudent instance from the route.
     */
    public function promotionStudent(): PromotionStudent
    {
        return $this->route('promotion_student');
    }
}