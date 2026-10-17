<?php

namespace App\Http\Requests\Storage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStorageProviderRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'config.root' => ['nullable', 'string', 'max:500'],
            'config.visibility' => ['nullable', 'string', 'in:public,private'],
            'config.key' => ['nullable', 'string', 'max:255'],
            'config.region' => ['nullable', 'string', 'max:64'],
            'config.bucket' => ['nullable', 'string', 'max:255'],
            'config.url' => ['nullable', 'string', 'max:500'],
            'config.endpoint' => ['nullable', 'string', 'max:500'],
            'config.use_path_style_endpoint' => ['nullable', 'boolean'],
            'secrets' => ['sometimes', 'array'],
            'secrets.secret' => ['nullable', 'string', 'max:500'],
            'clear_secrets' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
