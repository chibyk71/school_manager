<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionBatch;
use App\States\Promotion\Approved;
use Illuminate\Foundation\Http\FormRequest;

class ExecutePromotionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        if (! $batch instanceof PromotionBatch) {
            return false;
        }

        return $this->user()->can('promotions.execute') && $batch->status instanceof Approved;
    }

    public function rules(): array
    {
        return [];
    }
}
