<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverridePromotionStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        if (! $batch instanceof PromotionBatch) {
            return false;
        }

        // Single source of truth: PromotionPolicy::override
        return $this->user()->can('override', $batch);
    }

    public function rules(): array
    {
        return [
            'final_decision' => ['required', 'string', Rule::in(['promote', 'repeat', 'graduate'])],
            'override_reason' => 'required|string|min:5|max:1000',
        ];
    }
}
