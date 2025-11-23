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
 * Handles:
 *  - Email / enrollment_id login
 *  - Role-based sign-in permissions
 *  - Email verification
 *  - Rate limiting
 *  - System-wide admin bypass (no school required)
 *
 * @group Authentication
 */
class LoginRequest extends FormRequest
{
    /* --------------------------------------------------------------------- */
    /*  AUTHORIZATION                                                        */
    /* --------------------------------------------------------------------- */

    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled in the controller via permitted().
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /* --------------------------------------------------------------------- */
    /*  VALIDATION RULES                                                     */
    /* --------------------------------------------------------------------- */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login'      => ['required', 'string'], // email or enrollment_id
            'password'   => ['required', 'string'],
            'remember'   => ['required', 'boolean'],
            'school_id'  => ['nullable', 'exists:schools,id'],
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

    /* --------------------------------------------------------------------- */
    /*  AUTHENTICATION LOGIC                                                 */
    /* --------------------------------------------------------------------- */

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException If authentication fails or user is not allowed to sign in.
     */
    public function authenticate(): void
    {
        try {
            // -----------------------------------------------------------------
            // 1. Rate limiting
            // -----------------------------------------------------------------
            $this->ensureIsNotRateLimited();

            // -----------------------------------------------------------------
            // 2. Resolve active school (may be null for system-wide admins)
            // -----------------------------------------------------------------
            $school = GetSchoolModel(); // null = no school context

            // -----------------------------------------------------------------
            // 3. Determine login field
            // -----------------------------------------------------------------
            $loginField = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'enrollment_id';
            $credentials = [
                $loginField => $this->login,
                'password'  => $this->password,
            ];

            // -----------------------------------------------------------------
            // 4. Find user – **system admins have no school_id**
            // -----------------------------------------------------------------
            $userQuery = User::query()->where($loginField, $this->login);

            // If a school is selected, filter by it – unless user is system admin
            if ($school) {
                $userQuery->where(function ($q) use ($school) {
                    $q->where('school_id', $school->id)
                      ->orWhereNull('school_id'); // allow system admins
                });
            }

            $user = $userQuery->first();

            if (! $user) {
                $this->recordFailedAttempt($school, $loginField);
                throw ValidationException::withMessages([
                    'login' => trans('auth.failed'),
                ]);
            }

            // -----------------------------------------------------------------
            // 5. Determine if user is a system-wide admin
            // -----------------------------------------------------------------
            $isSystemAdmin = $this->isSystemWideAdmin($user);

            // -----------------------------------------------------------------
            // 6. Role-based sign-in permissions (school-specific or global)
            // -----------------------------------------------------------------
            if (! $this->userCanSignIn($user, $school, $isSystemAdmin)) {
                $this->recordFailedAttempt($school, $loginField);
                throw ValidationException::withMessages([
                    'login' => 'Sign-in not allowed for this user role.',
                ]);
            }

            // -----------------------------------------------------------------
            // 7. Email verification (only for school-bound users)
            // -----------------------------------------------------------------
            if (! $isSystemAdmin && $this->requiresEmailVerification($school) && ! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'login' => 'Email verification required.',
                ]);
            }

            // -----------------------------------------------------------------
            // 8. Final authentication attempt
            // -----------------------------------------------------------------
            if (! Auth::attempt($credentials, $this->boolean('remember'))) {
                $this->recordFailedAttempt($school, $loginField);
                throw ValidationException::withMessages([
                    'login' => trans('auth.failed'),
                ]);
            }

            // -----------------------------------------------------------------
            // 9. Clear rate limiter on success
            // -----------------------------------------------------------------
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

    /* --------------------------------------------------------------------- */
    /*  RATE LIMITING                                                        */
    /* --------------------------------------------------------------------- */

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException If rate limit is exceeded.
     */
    public function ensureIsNotRateLimited(): void
    {
        try {
            $school = GetSchoolModel();

            // System-wide login (no school) uses global throttle
            $authSettings = getMergedSettings('authentication', $school);
            $maxAttempts  = $authSettings['login_throttle_max'] ?? 5;
            $lockMinutes  = $authSettings['login_throttle_lock'] ?? 1;

            if (! RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts)) {
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
     * Includes school ID when available; falls back to 'global' for system admins.
     *
     * @return string
     */
    public function throttleKey(): string
    {
        $school   = GetSchoolModel();
        $schoolId = $school ? $school->id : 'global';
        return Str::transliterate(
            Str::lower($this->string('login')) . '|' . $this->ip() . '|' . $schoolId
        );
    }

    /* --------------------------------------------------------------------- */
    /*  HELPER METHODS                                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Determine if the user is a system-wide admin.
     *
     * System admins have **no school association** (school_id = null).
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    private function isSystemWideAdmin(User $user): bool
    {
        return ($user->schools()->count() === 0) || $user->hasRole('admin');
    }

    /**
     * Check if the user is allowed to sign in based on role settings.
     *
     * @param  \App\Models\User  $user
     * @param  mixed  $school
     * @param  bool  $isSystemAdmin
     * @return bool
     */
    private function userCanSignIn(User $user, $school, bool $isSystemAdmin): bool
    {
        // System admins bypass all role checks
        if ($isSystemAdmin) {
            return true;
        }

        // Load settings only if a school exists
        if (! $school) {
            return false;
        }

        $userSettings = getMergedSettings('user_management', $school);
        $allowedRoles = ['admin']; // admins always allowed

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

        return $user->hasAnyRole($allowedRoles, $school->id);
    }

    /**
     * Check if email verification is required for the current school.
     *
     * @param  mixed  $school
     * @return bool
     */
    private function requiresEmailVerification($school): bool
    {
        $authSettings = getMergedSettings('authentication', $school);
        return $authSettings['enable_email_verification'] ?? false;
    }

    /**
     * Record a failed login attempt for activity log and rate limiting.
     *
     * @param  mixed  $school
     * @param  string  $loginField
     * @return void
     */
    private function recordFailedAttempt($school, string $loginField): void
    {
        RateLimiter::hit($this->throttleKey());

        activity()
            ->withProperties([
                'school_id'   => $school?->id,
                'login_field' => $loginField,
                'input'       => $this->login,
            ])
            ->log('Failed login attempt');
    }
}
