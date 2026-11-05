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
 * - Failed OTP attempts are counted exactly once; lock duration is applied via RateLimiter::hit.
 *
 * @group Authentication
 */
class NewPasswordController extends Controller
{
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
     * Preconditions: valid ResetPasswordRequest (token, normalized email, confirmed password).
     * Postconditions on success: password updated, force_password_change cleared, attempt
     * counter cleared, PasswordReset event fired. Lifecycle is not promoted from Provisioned.
     *
     * Throttling: one failed OTP validation increments the counter once. Lock duration is the
     * decay seconds passed to RateLimiter::hit (default 60 minutes).
     */
    public function store(ResetPasswordRequest $request): RedirectResponse|JsonResponse
    {
        $throttleKey = null;
        $decayMinutes = 60;
        $maxAttempts = 5;

        try {
            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            $resolver = app(SettingsResolver::class);
            $allowPasswordReset = true;
            if ($school) {
                $scope = SettingsScope::school((string) $school->id);
                $allowPasswordReset = (bool) $resolver->get('authentication.allow_password_reset', $scope);
            }
            if (! $allowPasswordReset) {
                throw new \Exception('Password reset is not allowed.');
            }

            $normalizedEmail = AuthenticationEmail::normalize($request->input('email'));
            $throttleKey = 'password-reset|'.$normalizedEmail.'|'.$request->ip();

            if ($this->hasTooManyAttempts($throttleKey, $maxAttempts)) {
                throw new \Exception('Too many password reset attempts. Please try again later.');
            }

            $otpInstance = app(Otp::class);
            $otpResult = $otpInstance->validate($normalizedEmail, $request->input('token'));

            if (! ($otpResult->status ?? false)) {
                // Count this failure once; do not increment again in the catch block.
                $this->incrementAttempts($throttleKey, $decayMinutes);
                throw new \Exception('Invalid or expired OTP.');
            }

            // Re-check eligibility at completion — verification/enablement may have changed.
            $user = AuthenticationEmail::resolveEligibleAccount($normalizedEmail);
            if ($user === null) {
                $this->incrementAttempts($throttleKey, $decayMinutes);
                throw new \Exception('Unable to reset password for this email.');
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
        } catch (\Exception $e) {
            Log::warning('Password reset failed: '.$e->getMessage());

            // OTP failures already incremented above; avoid double-count for those messages.
            $alreadyCounted = str_contains($e->getMessage(), 'Invalid or expired OTP')
                || str_contains($e->getMessage(), 'Unable to reset password for this email');

            if ($throttleKey !== null && ! $alreadyCounted) {
                $this->incrementAttempts($throttleKey, $decayMinutes);
            }

            return $this->respondWithError($request, $e->getMessage());
        }
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
