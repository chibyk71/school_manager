<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Support\Auth\AuthenticationEmail;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Ichtrojan\Otp\Otp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Complete password recovery using an OTP issued against a normalized Person email.
 *
 * Domain boundaries:
 * - Re-checks authentication-email eligibility at completion time (state may have changed).
 * - Does not activate Provisioned accounts or alter lifecycle beyond credential update.
 * - Does not require School workspace context.
 * - Failed OTP attempts are counted exactly once via explicit control flow (not message matching).
 * - Requests that are already locked out do not further increment the counter.
 *
 * @group Authentication
 */
class NewPasswordController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_MINUTES = 60;

    /**
     * Display the password reset form.
     */
    public function create(Request $request): InertiaResponse|JsonResponse|RedirectResponse
    {
        try {
            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            $resolver = app(SettingsResolver::class);
            $allowPasswordReset = true;
            if ($school) {
                $scope = SettingsScope::school((string) $school->id);
                $allowPasswordReset = (bool) $resolver->get('authentication.allow_password_reset', $scope);
            }
            if (! $allowPasswordReset) {
                return $this->respondWithError($request, 'Password reset is not allowed.', 403);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'school_id' => $school?->id,
                    'token' => $request->query('token'),
                    'email' => $request->query('email'),
                ], 200);
            }

            return Inertia::render('Auth/ResetPassword', [
                'school_id' => $school?->id,
                'token' => $request->token,
                'email' => $request->query('email'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to display password reset view: '.$e->getMessage());

            return $this->respondWithError($request, 'Failed to load password reset page.');
        }
    }

    /**
     * Validate OTP and set a new password for an eligible Authentication Account.
     *
     * Preconditions: valid ResetPasswordRequest (token, email, confirmed password).
     * Postconditions on success: password updated, force_password_change cleared, attempt
     * counter cleared, PasswordReset event fired. Lifecycle is not promoted from Provisioned.
     *
     * Throttling control flow:
     * - If already locked, return error without incrementing.
     * - Invalid OTP or post-OTP ineligibility increments once then returns.
     * - Other failures after lock check do not use the OTP attempt counter unless they
     *   represent a completed OTP validation attempt path.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse|JsonResponse
    {
        $normalizedEmail = AuthenticationEmail::normalize($request->input('email'));
        $throttleKey = $this->completionThrottleKey($normalizedEmail, $request->ip());

        try {
            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            $resolver = app(SettingsResolver::class);
            $allowPasswordReset = true;
            if ($school) {
                $scope = SettingsScope::school((string) $school->id);
                $allowPasswordReset = (bool) $resolver->get('authentication.allow_password_reset', $scope);
            }
            if (! $allowPasswordReset) {
                return $this->respondWithError($request, 'Password reset is not allowed.', 403);
            }

            if ($this->hasTooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
                return $this->respondWithError($request, 'Too many password reset attempts. Please try again later.', 429);
            }

            $otpInstance = app(Otp::class);
            $otpResult = $otpInstance->validate($normalizedEmail, $request->input('token'));

            if (! ($otpResult->status ?? false)) {
                $this->incrementAttempts($throttleKey, self::DECAY_MINUTES);

                return $this->respondWithError($request, 'Invalid or expired OTP.');
            }

            // Re-check eligibility at completion — verification/enablement may have changed.
            $user = AuthenticationEmail::resolveEligibleAccount($normalizedEmail);
            if ($user === null) {
                $this->incrementAttempts($throttleKey, self::DECAY_MINUTES);

                return $this->respondWithError($request, 'Unable to reset password for this email.');
            }

            // Credential update only — does not activate Provisioned accounts.
            $user->forceFill([
                'password' => $request->input('password'),
                'force_password_change' => false,
            ])->save();

            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school?->id])
                ->log('Password reset successfully');

            event(new PasswordReset($user));

            $this->clearAttempts($throttleKey);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password reset successfully.'], 200);
            }

            return redirect()->route('login')
                ->with('status', 'Password reset successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Password reset failed unexpectedly', ['exception' => $e::class]);

            // Unexpected infrastructure errors: do not treat as OTP guesses.
            return $this->respondWithError($request, 'Password reset failed. Please try again.');
        }
    }

    /**
     * Rate-limit key for recovery completion (OTP validation attempts).
     * Email component is hashed; IP remains for client isolation.
     */
    protected function completionThrottleKey(string $normalizedEmail, string $ip): string
    {
        return 'password-reset|'.hash('sha256', $normalizedEmail).'|'.$ip;
    }

    protected function hasTooManyAttempts(string $throttleKey, int $maxAttempts): bool
    {
        return app('Illuminate\Cache\RateLimiter')->tooManyAttempts($throttleKey, $maxAttempts);
    }

    protected function incrementAttempts(string $throttleKey, int $decayMinutes = 60): void
    {
        app('Illuminate\Cache\RateLimiter')->hit($throttleKey, $decayMinutes * 60);
    }

    protected function clearAttempts(string $throttleKey): void
    {
        app('Illuminate\Cache\RateLimiter')->clear($throttleKey);
    }

    protected function respondWithError(Request $request, string $message, int $statusCode = 400): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->withErrors(['token' => $message]);
    }
}
