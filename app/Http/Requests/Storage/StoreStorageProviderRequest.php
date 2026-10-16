<?php

namespace App\Http\Requests\Storage;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStorageProviderRequest extends FormRequest
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
            'driver' => ['required', Rule::enum(StorageProviderDriver::class)],
            'ownership' => ['required', Rule::enum(StorageProviderOwnership::class)],
            'school_id' => ['nullable', 'uuid'],
            'config' => ['nullable', 'array'],
            'config.root' => ['nullable', 'string', 'max:500'],
            'config.visibility' => ['nullable', 'string', 'in:public,private'],
            'config.key' => ['nullable', 'string', 'max:255'],
            'config.region' => ['nullable', 'string', 'max:64'],
            'config.bucket' => ['nullable', 'string', 'max:255'],
            'config.url' => ['nullable', 'string', 'max:500'],
            'config.endpoint' => ['nullable', 'string', 'max:500'],
            'config.use_path_style_endpoint' => ['nullable', 'boolean'],
            'secrets' => ['nullable', 'array'],
            'secrets.secret' => ['nullable', 'string', 'max:500'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
