<?php

namespace App\Http\Requests\Auth;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login via username, verified+enabled Person primary email, or verified+enabled auth phone.
 * Authentication eligibility is independent of roles, school membership, and dashboards.
 * Shared communication emails: if more than one eligible account matches, fail closed.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'school_id' => ['nullable', 'exists:schools,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'login' => 'username, email, or phone',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));
        $password = (string) $this->input('password');

        $user = $this->resolveAccount($login);

        if ($user === null || ! $this->passwordMatches($user, $password)) {
            $this->failLogin();
        }

        if (! $user->canAuthenticate()) {
            $this->failLogin('This account is not eligible to sign in.');
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());

        $user->forceFill(['last_login_at' => now()])->save();
    }

    /**
     * Resolve Authentication Account by username, eligible auth email, or eligible auth phone.
     * Email/phone: require exactly one eligible account; otherwise fail closed.
     */
    protected function resolveAccount(string $login): ?User
    {
        $byUsername = User::query()
            ->where('username', strtolower($login))
            ->first();
        if ($byUsername) {
            return $byUsername;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $normalized = strtolower($login);
            $candidates = User::query()
                ->whereHas('profile', function ($q) use ($normalized) {
                    $q->whereRaw('LOWER(email) = ?', [$normalized]);
                })
                ->whereNotNull('auth_email_verified_at')
                ->where('auth_email_enabled', true)
                ->get()
                ->filter(fn (User $u) => $u->canUseEmailForLogin())
                ->values();

            if ($candidates->count() === 1) {
                return $candidates->first();
            }

            // Zero or multiple eligible accounts → fail closed (use username)
            return null;
        }

        // Phone login is intentionally not accepted until a possession-proof
        // verification challenge workflow exists (Phase 1 boundary).
        // markAuthPhoneVerified() alone is not proof of ownership.

        return null;
    }

    protected function passwordMatches(User $user, string $password): bool
    {
        if ($user->password === null || $user->password === '') {
            return false;
        }

        return Hash::check($password, $user->password);
    }

    protected function failLogin(?string $message = null): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => $message ?? trans('auth.failed'),
        ]);
    }

    public function ensureIsNotRateLimited(): void
    {
        try {
            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            $authSettings = function_exists('getMergedSettings')
                ? getMergedSettings('authentication', $school)
                : [];
            $maxAttempts = $authSettings['login_throttle_max'] ?? 5;

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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Rate limiting check failed: {$e->getMessage()}");
            throw ValidationException::withMessages([
                'login' => 'Failed to process login request.',
            ]);
        }
    }

    public function throttleKey(): string
    {
        // Authentication throttling is Tenant/application-scoped.
        // Do not partition by client-selected School workspace.
        return Str::transliterate(
            Str::lower($this->string('login')).'|'.$this->ip()
        );
    }
}
