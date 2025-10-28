<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for authentication settings.
 *
 * @group Settings
 */
class AuthenticationSettingsRequest extends FormRequest
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
            // Login Throttling
            'login_throttle_max' => ['required', 'integer', 'min:1', 'max:20'],
            'login_throttle_lock' => ['required', 'integer', 'min:1', 'max:60'],
            
            // Password Reset
            'reset_password_token_life' => ['required', 'integer', 'min:1', 'max:120'],
            'allow_password_reset' => ['required', 'boolean'],
            'password_reset_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            
            // Email Verification
            'enable_email_verification' => ['required', 'boolean'],
            'otp_length' => ['required', 'integer', 'min:4', 'max:8'],
            'otp_validity' => ['required', 'integer', 'min:1', 'max:30'],
            'allow_otp_fallback' => ['required', 'boolean'],
            
            // Registration
            'allow_user_registration' => ['required', 'boolean'],
            'account_approval' => ['required', 'boolean'],
            'oAuth_registration' => ['required', 'boolean'],
            'show_terms_on_registration' => ['required', 'boolean'],
            
            // Password Confirmation
            'require_password_confirmation' => ['required', 'boolean'],
            'password_confirmation_ttl' => ['required', 'integer', 'min:300', 'max:86400'],
            
            // Password Change
            'allow_password_change' => ['required', 'boolean'],
            
            // Password Rules
            'password_min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'password_require_letters' => ['required', 'boolean'],
            'password_require_mixed_case' => ['required', 'boolean'],
            'password_require_numbers' => ['required', 'boolean'],
            'password_require_symbols' => ['required', 'boolean'],
            
            // Rate Limiting
            'registration_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'registration_lock_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'password_update_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'password_update_lock_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'otp_verification_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login_throttle_max' => 'login throttle maximum attempts',
            'login_throttle_lock' => 'login throttle lockout time (minutes)',
            'reset_password_token_life' => 'password reset token lifetime (minutes)',
            'allow_password_reset' => 'allow password reset',
            'enable_email_verification' => 'enable email verification',
            'otp_length' => 'OTP length',
            'otp_validity' => 'OTP validity (minutes)',
            'allow_otp_fallback' => 'allow OTP fallback (manual verification)',
            'allow_user_registration' => 'allow user registration',
            'account_approval' => 'require account approval',
            'oAuth_registration' => 'enable OAuth registration',
            'show_terms_on_registration' => 'show terms on registration',
            'require_password_confirmation' => 'require password confirmation',
            'password_confirmation_ttl' => 'password confirmation TTL (seconds)',
            'allow_password_change' => 'allow password change',
            'password_min_length' => 'minimum password length',
            'password_require_letters' => 'require letters in password',
            'password_require_mixed_case' => 'require mixed case in password',
            'password_require_numbers' => 'require numbers in password',
            'password_require_symbols' => 'require symbols in password',
            'registration_max_attempts' => 'registration maximum attempts',
            'registration_lock_minutes' => 'registration lockout time (minutes)',
            'password_update_max_attempts' => 'password update maximum attempts',
            'password_update_lock_minutes' => 'password update lockout time (minutes)',
            'otp_verification_max_attempts' => 'OTP verification maximum attempts',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login_throttle_max.max' => 'Login throttle maximum attempts cannot exceed 20.',
            'login_throttle_lock.max' => 'Login throttle lockout time cannot exceed 60 minutes.',
            'reset_password_token_life.max' => 'Password reset token lifetime cannot exceed 120 minutes.',
            'otp_length.min' => 'OTP length must be at least 4 digits.',
            'otp_length.max' => 'OTP length cannot exceed 8 digits.',
            'otp_validity.max' => 'OTP validity cannot exceed 30 minutes.',
            'password_confirmation_ttl.min' => 'Password confirmation TTL must be at least 5 minutes (300 seconds).',
            'password_confirmation_ttl.max' => 'Password confirmation TTL cannot exceed 24 hours (86400 seconds).',
            'password_min_length.min' => 'Password minimum length must be at least 6 characters.',
            'password_min_length.max' => 'Password maximum length cannot exceed 128 characters.',
            'registration_max_attempts.max' => 'Registration maximum attempts cannot exceed 10.',
            'registration_lock_minutes.max' => 'Registration lockout time cannot exceed 60 minutes.',
            'password_update_max_attempts.max' => 'Password update maximum attempts cannot exceed 10.',
            'password_update_lock_minutes.max' => 'Password update lockout time cannot exceed 60 minutes.',
            'otp_verification_max_attempts.max' => 'OTP verification maximum attempts cannot exceed 10.',
        ];
    }
}