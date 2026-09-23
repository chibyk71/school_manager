<?php

namespace App\Http\Requests\Settings\DynamicEnum;

use Illuminate\Foundation\Http\FormRequest;

class StoreOptionRequest extends FormRequest
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
            'value' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'mode' => ['sometimes', 'in:option,override'],
            'tenant' => ['sometimes', 'boolean'],
        ];
    }
}
