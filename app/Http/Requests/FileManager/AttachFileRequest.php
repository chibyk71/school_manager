<?php

namespace App\Http\Requests\FileManager;

use Illuminate\Foundation\Http\FormRequest;

class AttachFileRequest extends FormRequest
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
            'owner_type' => ['required', 'string', 'max:255'],
            'owner_id' => ['required', 'uuid'],
            'purpose' => ['nullable', 'string', 'max:64'],
            'lifecycle' => ['nullable', 'string', 'max:32'],
        ];
    }
}
