<?php

namespace App\Http\Requests\Settings\DynamicEnum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDefinitionPresentationRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
