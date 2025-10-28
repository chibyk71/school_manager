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
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization handled by permitted() in controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'school_id' => ['required', 'exists:schools,id'],
        ];
    }

    /**
     * Validate the user's password.
     *
     * @throws ValidationException If password is invalid.
     */
    public function validatePassword(): void
    {
        $user = $this->user();
        if (!$user || !Auth::guard('web')->validate([
            'email' => $user->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }
    }
}
