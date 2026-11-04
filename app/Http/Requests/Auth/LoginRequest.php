<?php

namespace App\Http\Requests\Auth;

use App\Enums\Auth\AccountLifecycle;
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
     * Resolve Authentication Account by username, auth email, or auth phone.
     */
    protected function resolveAccount(string $login): ?User
    {
        // 1. Username (primary identifier)
        $byUsername = User::query()
            ->where('username', strtolower($login))
            ->first();
        if ($byUsername) {
            return $byUsername;
        }

        // 2. Person primary email — only if verified + enabled on the account
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $normalized = strtolower($login);
            $profile = Profile::query()
                ->whereRaw('LOWER(email) = ?', [$normalized])
                ->whereNotNull('user_id')
                ->first();

            if ($profile?->user) {
                $account = $profile->user;
                if ($account->canUseEmailForLogin()
                    && strtolower((string) $profile->email) === $normalized) {
                    return $account;
                }
            }

            return null;
        }

        // 3. Authentication phone — only if verified + enabled
        $digits = preg_replace('/[^\d+]/', '', $login);
        if ($digits !== '') {
            $byPhone = User::query()
                ->where('auth_phone', $digits)
                ->first();
            if ($byPhone && $byPhone->canUsePhoneForLogin()) {
                return $byPhone;
            }
        }

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
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $schoolId = $school ? $school->id : 'global';

        return Str::transliterate(
            Str::lower($this->string('login')).'|'.$this->ip().'|'.$schoolId
        );
    }
}
