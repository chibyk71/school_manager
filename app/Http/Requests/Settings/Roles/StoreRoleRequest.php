<?php

namespace App\Http\Requests\Settings\Roles;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_\-]*$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Technical name may only contain lowercase letters, numbers, underscores, and hyphens.',
        ];
    }
}
