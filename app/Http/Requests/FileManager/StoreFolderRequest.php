<?php

namespace App\Http\Requests\FileManager;

use App\Enums\FileManager\Capability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            'module_code' => ['nullable', 'string', 'max:64'],
            'default_storage_provider_id' => ['nullable', 'uuid'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', Rule::in(Capability::values())],
            'ownership' => ['nullable', 'string', Rule::in(['system', 'school'])],
        ];
    }
}
