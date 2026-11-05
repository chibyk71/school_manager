<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Password recovery submission (OTP token + new password).
 *
 * Account recovery is Tenant/application scoped and must not require a selected School.
 * Password complexity uses application defaults rather than School-scoped settings.
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'The password confirmation does not match.',
            'token.required' => 'Please enter the verification code.',
        ];
    }

    public function attributes(): array
    {
        return [
            'token' => 'verification code',
            'email' => 'email address',
            'password' => 'new password',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->email)),
            ]);
        }
    }
}
