<?php

namespace App\Http\Requests\Settings\DynamicEnum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOptionPresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
