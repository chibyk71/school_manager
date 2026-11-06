<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laragear\TwoFactor\Facades\Auth2FA;

/**
 * MFA enrollment and login-challenge flows (Laragear Two-Factor v3).
 *
 * Enrollment: createTwoFactorAuth → confirmTwoFactorAuth (enable).
 * Login challenge: validateTwoFactorCode (TOTP or recovery code).
 * Do not use confirmTwoFactorAuth for login challenges — that is enrollment only.
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
     */
    public function challenge()
    {
        if (! Auth::check() || ! Auth::user()->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if (class_exists(Auth2FA::class) && Auth2FA::hasSafeDeviceCookie(request())) {
            return redirect()->intended();
        }

        if (request()->session()->get('auth.2fa_confirmed_at')) {
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
            // Uniform failure message — do not reveal whether TOTP vs recovery was expected.
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->put('auth.2fa_confirmed_at', time());

        if ($request->boolean('remember') && method_exists($user, 'addSafeDevice')) {
            $user->addSafeDevice($request);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    protected function challengeThrottleKey(string $userId, ?string $ip): string
    {
        return '2fa-challenge:'.$userId.'|'.($ip ?? 'unknown');
    }
}
