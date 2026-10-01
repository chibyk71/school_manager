<?php

namespace App\Http\Requests\Settings\Roles;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateRoleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'disabled' => ['required', 'boolean'],
        ];
    }
}
