<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * MFA enrollment and login-challenge flows (Laragear Two-Factor v3.0.0).
 *
 * Enrollment: createTwoFactorAuth → confirmTwoFactorAuth (enable).
 * Login challenge: validateTwoFactorCode (TOTP or recovery code).
 * Safe devices: User::isSafeDevice / addSafeDevice when config two-factor.safe_devices.enabled.
 * Do not use confirmTwoFactorAuth for login challenges — that is enrollment only.
 * Do not call nonexistent Auth2FA::hasSafeDeviceCookie (facade is login-helper only).
 */
class TwoFactorController extends Controller
{
    /**
     * Begin MFA enrollment: generate shared secret and show QR material.
     */
    public function enable()
    {
        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.edit')->with('info', 'Two-factor authentication is already enabled.');
        }

        $totp = $user->createTwoFactorAuth();

        return Inertia::render('Auth/TwoFactor/Enable', [
            'qrCode' => $totp->toQr(),
            'uri' => $totp->toUri(),
            'secret' => $totp->toString(),
        ]);
    }

    /**
     * Confirm enrollment with a TOTP code and enable MFA for the account.
     *
     * @param  Request  $request  Must include code (current TOTP).
     * @return \Inertia\Response
     *
     * @throws ValidationException When the code is invalid.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = Auth::user();

        // Enrollment confirmation only — not a login challenge.
        if (! $user->confirmTwoFactorAuth($request->code)) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        activity()
            ->performedOn($user)
            ->log('Two-factor authentication enabled');

        return Inertia::render('Auth/TwoFactor/RecoveryCodes', [
            'recoveryCodes' => $user->getRecoveryCodes()->toArray(),
        ]);
    }

    /**
     * Disable MFA after a valid TOTP (enrollment/management path).
     */
    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = Auth::user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.edit')->with('info', 'Two-factor authentication is not enabled.');
        }

        // Require a current TOTP before disabling (not a recovery code for this path).
        if (! $user->validateTwoFactorCode($request->code, useRecoveryCodes: false)) {
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
        }

        $user->disableTwoFactorAuth();

        activity()
            ->performedOn($user)
            ->log('Two-factor authentication disabled');

        return redirect()->route('profile.edit')->with('success', 'Two-factor authentication disabled.');
    }

    /**
     * Show the post-login MFA challenge form.
     *
     * Skips the challenge when the account has no MFA, the session already
     * confirmed MFA, or (when enabled) the request presents a valid safe device.
     */
    public function challenge(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if ($request->session()->get('auth.2fa_confirmed_at')) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($this->isSafeDeviceBypassAllowed($user, $request)) {
            $request->session()->put('auth.2fa_confirmed_at', time());

            return redirect()->intended(route('dashboard', absolute: false));
        }

        return Inertia::render('Auth/TwoFactor/Challenge');
    }

    /**
     * Complete the login MFA challenge with TOTP or recovery code.
     *
     * Uses Laragear v3 validateTwoFactorCode() — never confirmTwoFactorAuth().
     * Brute-force protection: account + IP key, 5 attempts, 15-minute decay.
     * Locked requests do not increment the counter; success clears it.
     *
     * @param  Request  $request  code (required), remember (optional safe-device flag).
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws ValidationException On invalid code or active lockout.
     */
    public function verify(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $throttleKey = $this->challengeThrottleKey($user->id, $request->ip());

        // Blocked requests must not increment the failure counter again.
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'code' => "Too many verification attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $request->validate([
            'code' => 'required|string',
            'remember' => 'sometimes|boolean',
        ]);

        $code = (string) $request->input('code');

        // Login challenge: TOTP or unused recovery code (package semantics).
        $valid = $user->validateTwoFactorCode($code, useRecoveryCodes: true);

        if (! $valid) {
            RateLimiter::hit($throttleKey, 60 * 15);
            // Uniform failure message — do not reveal TOTP vs recovery expectation.
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->put('auth.2fa_confirmed_at', time());

        // Remember device only when safe devices are enabled and the client asked.
        if ($request->boolean('remember') && $this->safeDevicesEnabled() && method_exists($user, 'addSafeDevice')) {
            $user->addSafeDevice($request);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Whether safe-device bypass is allowed for this request (config + User API).
     */
    protected function isSafeDeviceBypassAllowed($user, Request $request): bool
    {
        if (! $this->safeDevicesEnabled()) {
            return false;
        }

        return method_exists($user, 'isSafeDevice') && $user->isSafeDevice($request);
    }

    protected function safeDevicesEnabled(): bool
    {
        return (bool) config('two-factor.safe_devices.enabled', false);
    }

    protected function challengeThrottleKey(string $userId, ?string $ip): string
    {
        return '2fa-challenge:'.$userId.'|'.($ip ?? 'unknown');
    }
}
