<?php

namespace App\Http\Requests\FileManager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:51200'], // 50 MB advisory; provider may impose more
            'folder_id' => ['nullable', 'uuid'],
            'storage_provider_id' => ['nullable', 'uuid'],
            'managed_by' => ['nullable', 'string', Rule::in(['file_manager', 'module'])],
            'module_code' => ['nullable', 'string', 'max:64'],
            'ownership' => ['nullable', 'string', Rule::in(['system', 'school'])],
        ];
    }
}
