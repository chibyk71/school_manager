<?php

namespace App\Http\Requests\FileManager;

use App\Enums\FileManager\Capability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFolderRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'default_storage_provider_id' => ['sometimes', 'nullable', 'uuid'],
            'capabilities' => ['sometimes', 'nullable', 'array'],
            'capabilities.*' => ['string', Rule::in(Capability::values())],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
