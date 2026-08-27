<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionBatch;
use App\States\Promotion\Pending;
use App\States\Promotion\Reviewing;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePromotionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        if (! $batch instanceof PromotionBatch) {
            return false;
        }

        if (! $this->user()->can('promotions.approve')) {
            return false;
        }

        return $batch->status instanceof Pending || $batch->status instanceof Reviewing;
    }

    public function rules(): array
    {
        return [
            'approval_comments' => 'nullable|string|max:1000',
        ];
    }
}
