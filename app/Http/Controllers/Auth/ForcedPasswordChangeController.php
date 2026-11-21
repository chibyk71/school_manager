<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserManagement\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Restricted first-login / forced password-change flow.
 * Available without school membership or roles.
 *
 * Security: Enforces MFA completion, email OTP verification, or
 * alternate verification before allowing password replacement.
 * Safe-device cookies do not bypass this verification.
 */
class ForcedPasswordChangeController extends Controller
{
    public function edit(Request $request): InertiaResponse|JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->requiresPasswordChange()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'force_password_change' => true,
                'username' => $user->username,
            ]);
        }

        return Inertia::render('Auth/ForcePasswordChange', [
            'username' => $user->username,
        ]);
    }

    public function update(Request $request, UserAccountService $accounts): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            throw ValidationException::withMessages(['password' => 'Unauthenticated.']);
        }

        if (! $user->requiresPasswordChange()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No password change required.'])
                : redirect()->intended(route('dashboard', absolute: false));
        }

        // Security: verify identity before allowing password replacement
        $this->ensureVerificationBeforePasswordChange($user, $request);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'force_password_change' => false,
        ])->save();

        // Keep lifecycle Active; only clear the force flag.
        $accounts->setForcePasswordChange($user->fresh(), false);

        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Password updated.']);
        }

/**
     * Verify that the user has completed a fresh authentication challenge
     * before allowing password replacement.
     *
     * Priority:
     * 1. MFA enabled -> require fresh MFA (session auth.2fa_confirmed_at).
     *    Safe-device cookies do NOT bypass for password-changing ops.
     * 2. No MFA, verified email -> require email OTP verification.
     * 3. No eligible method -> fail closed.
     *
     * @throws ValidationException
     */
    protected function ensureVerificationBeforePasswordChange(User $user, Request $request): void
    {
        // Case 1: MFA enabled — require fresh challenge
        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            // Safe devices are NOT accepted for password-changing operations.
            // Only a fresh TOTP challenge recorded in the session is valid.
            if (! $request->session()->has('auth.2fa_confirmed_at')) {
                $msg = 'Two-factor authentication must be completed before changing your password.';
                if ($request->expectsJson()) {
                    throw ValidationException::withMessages(['verification' => $msg]);
                }
                throw ValidationException::withMessages(['password' => $msg]);
            }
            return;
        }

        // Case 2: No MFA, verified email enabled
        if ($user->auth_email_verified_at !== null && $user->auth_email_enabled === true) {
            // Require fresh email OTP verification in this session
            if ($request->session()->has('auth.email_otp_confirmed_at')) {
                return;
            }
            $msg = 'Please verify your email before changing your password.';
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['verification' => $msg]);
            }
            throw ValidationException::withMessages(['password' => $msg]);
        }

        // Case 3: No eligible verification method — fail closed
        $msg = 'No eligible verification method available. Cannot change password.';
        if ($request->expectsJson()) {
            throw ValidationException::withMessages(['verification' => $msg]);
        }
        throw ValidationException::withMessages(['password' => $msg]);
    }
        return redirect()->intended(route('dashboard', absolute: false));
    }
}
