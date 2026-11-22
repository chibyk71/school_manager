<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Request validation for confirming user passwords.
 *
 * @group Authentication
 */
class ConfirmPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            // School workspace is optional — password confirmation is account-scoped.
            'school_id' => ['nullable', 'exists:schools,id'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function validatePassword(): void
    {
        $user = $this->user();
        if (
            ! $user
            || ! Auth::guard('web')->validate([
                'username' => $user->username,
                'password' => $this->input('password'),
            ])
        ) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }
    }
}
