<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Request validation for user login in a multi-tenant school system.
 *
 * @group Authentication
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization handled in controller via permitted()
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
            'login' => ['required', 'string'], // Accepts email or enrollment_id
            'password' => ['required', 'string'],
            'remember' => ['required', 'boolean'],
            'school_id' => ['required', 'exists:schools,id'],
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
            'login' => 'email or enrollment ID',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException If authentication fails or user is not allowed to sign in.
     */
    public function authenticate(): void
    {
        try {
            // Ensure not rate limited
            $this->ensureIsNotRateLimited();

            // Get active school
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Retrieve settings
            $userSettings = getMergedSettings('user_management', $school);

            // Determine login field
            $loginField = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'enrollment_id';
            $credentials = [
                $loginField => $this->login,
                'password' => $this->password,
            ];

            // Check user existence and role permissions
            $user = User::where($loginField, $this->login)
                ->where('school_id', $school->id)
                ->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'login' => trans('auth.failed'),
                ]);
            }

            // Verify role-based sign-in permissions
            $allowedRoles = [];
            if ($userSettings['allow_student_signin'] ?? false) {
                $allowedRoles[] = 'student';
            }
            if ($userSettings['allow_parent_signin'] ?? false) {
                $allowedRoles[] = 'guardian';
            }
            if ($userSettings['allow_teacher_signin'] ?? false) {
                $allowedRoles[] = 'teacher';
            }
            if ($userSettings['allow_staff_signin'] ?? false) {
                $allowedRoles[] = 'staff';
            }
            $allowedRoles[] = 'admin'; // Admins always allowed

            if (!$user->hasAnyRole($allowedRoles, $school->id)) {
                throw ValidationException::withMessages([
                    'login' => 'Sign-in not allowed for this user role.',
                ]);
            }

            // Check email verification if required
            $authSettings = getMergedSettings('authentication', $school);
            if (($authSettings['enable_email_verification'] ?? false) && !$user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'login' => 'Email verification required.',
                ]);
            }

            // Attempt authentication
            if (!Auth::attempt($credentials, $this->boolean('remember'))) {
                RateLimiter::hit($this->throttleKey());

                // Log failed attempt
                activity()
                    ->withProperties([
                        'school_id' => $school->id,
                        'login_field' => $loginField,
                        'input' => $this->login,
                    ])
                    ->log('Failed login attempt');

                throw ValidationException::withMessages([
                    'login' => trans('auth.failed'),
                ]);
            }

            // Clear rate limiter
            RateLimiter::clear($this->throttleKey());
        } catch (ValidationException $e) {
            Log::warning("Login validation failed: {$e->getMessage()}");
            throw $e;
        } catch (\Exception $e) {
            Log::error("Login attempt failed: {$e->getMessage()}");
            throw ValidationException::withMessages([
                'login' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException If rate limit is exceeded.
     */
    public function ensureIsNotRateLimited(): void
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $authSettings = getMergedSettings('authentication', $school);
            $maxAttempts = $authSettings['login_throttle_max'] ?? 5;
            $lockMinutes = $authSettings['login_throttle_lock'] ?? 1;

            if (!RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts)) {
                return;
            }

            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error("Rate limiting check failed: {$e->getMessage()}");
            throw ValidationException::withMessages([
                'login' => 'Failed to process login request.',
            ]);
        }
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * @return string
     */
    public function throttleKey(): string
    {
        $school = GetSchoolModel();
        $schoolId = $school ? $school->id : 'unknown';
        return Str::transliterate(Str::lower($this->string('login')) . '|' . $this->ip() . '|' . $schoolId);
    }
}
