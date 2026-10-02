<?php

namespace App\Http\Requests\Settings\Roles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disabled' => ['required', 'boolean'],
        ];
    }
}
