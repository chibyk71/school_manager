<?php

namespace App\Http\Requests\Auth;

use App\Services\AuthenticationSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Reset Password Request with Dynamic Password Policy
 *
 * @group Authentication
 */
class ResetPasswordRequest extends FormRequest
{
    public function __construct(
        
    ) {}

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $settings = $this->authSettings->getMergedSettings(
            schoolId: $this->input('school_id')
        );

        // Build dynamic password rule based on school + global settings
        $passwordRule = Password::min($settings->password_min_length ?? 8);

        if ($settings->password_require_uppercase ?? true) {
            $passwordRule = $passwordRule->mixedCase();
        }

        if ($settings->password_require_numbers ?? true) {
            $passwordRule = $passwordRule->numbers();
        }

        if ($settings->password_require_symbols ?? true) {
            $passwordRule = $passwordRule->symbols();
        }

        if ($settings->password_require_uncompromised ?? true) {
            $passwordRule = $passwordRule->uncompromised();
        }

        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                $passwordRule,
            ],
            'school_id' => ['required', 'exists:schools,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $settings = $this->authSettings->getMergedSettings(
            schoolId: $this->input('school_id')
        );

        $min = $settings->password_min_length ?? 8;
        $requirements = [];

        if ($settings->password_require_uppercase ?? true)  $requirements[] = 'one uppercase letter';
        if ($settings->password_require_lowercase ?? true)  $requirements[] = 'one lowercase letter';
        if ($settings->password_require_numbers ?? true)    $requirements[] = 'one number';
        if ($settings->password_require_symbols ?? true)    $requirements[] = 'one symbol';

        $requirementText = implode(', ', $requirements);

        return [
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => "Your password must be at least {$min} characters.",
            'password.*' => "Your password must contain at least: {$requirementText}.",
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'token' => 'verification code',
            'email' => 'email address',
            'password' => 'new password',
            'school_id' => 'institution',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Normalize email
        $this->merge([
            'email' => strtolower(trim($this->email)),
        ]);
    }
}