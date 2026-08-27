<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Foundation\Http\FormRequest;

class CancelPromotionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        if (! $batch instanceof PromotionBatch) {
            return false;
        }

        return $this->user()->can('promotions.cancel') && $batch->status->canBeCancelled();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for cancelling this promotion batch.',
            'reason.min' => 'Cancellation reason must be at least 10 characters long.',
        ];
    }
}
