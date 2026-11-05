<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\ResetPasswordNotification;
use App\Support\Auth\AuthenticationEmail;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Initiate password recovery for an Authentication Account.
 *
 * Recovery is a Tenant/application authentication capability. It does not require
 * School membership or an active School workspace. The Person primary email
 * (profiles.email) is used only when the linked account has verified and enabled
 * authentication email. Soft-deleted Persons and shared-email ambiguity fail closed
 * with non-revealing client responses.
 *
 * @group Authentication
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset request form.
     *
     * School context is optional for branding/settings only; absence of a School
     * does not block recovery initiation.
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
                    'status' => session('status'),
                    'school_id' => $school?->id,
                    'sms_enabled' => false,
                ], 200);
            }

            return Inertia::render('Auth/ForgotPassword', [
                'status' => session('status'),
                'school_id' => $school?->id,
                'sms_enabled' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to display password reset link view: '.$e->getMessage());

            return $this->respondWithError($request, 'Failed to load password reset page.');
        }
    }

    /**
     * Request a recovery OTP for an eligible Authentication Account.
     *
     * Business rules:
     * - Email is normalized once and used for lookup, OTP identifier, notification
     *   destination, and throttle key.
     * - Eligibility requires verified + enabled auth email on the linked account.
     * - Enrollment-ID recovery is not supported in this phase.
     * - SMS recovery is disabled (OTP must not be sent to a caller-supplied phone).
     * - Public responses do not distinguish missing, ineligible, or ambiguous emails
     *   beyond a generic failure when resolution is not exactly one eligible account.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $throttleKey = null;

        try {
            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            $resolver = app(SettingsResolver::class);
            $scope = null;
            $allowPasswordReset = true;
            if ($school) {
                $scope = SettingsScope::school((string) $school->id);
                $allowPasswordReset = (bool) $resolver->get('authentication.allow_password_reset', $scope);
            }
            if (! $allowPasswordReset) {
                throw new \Exception('Password reset is not allowed.');
            }

            $request->validate([
                'email' => ['required_without:enrollment_id', 'email', 'nullable'],
                'enrollment_id' => ['required_without:email', 'string', 'nullable'],
                'delivery_method' => ['nullable', 'string', 'in:email'],
            ], [
                'email.required_without' => 'Email is required if enrollment ID is not provided.',
                'enrollment_id.required_without' => 'Enrollment ID is required if email is not provided.',
            ]);

            if ($request->filled('enrollment_id')) {
                // Fail closed: enrollment is an academic identifier, not an Authentication Account id.
                throw new \Exception('Password reset by enrollment ID is not supported. Use the account email.');
            }

            if ($request->input('delivery_method') === 'sms') {
                throw new \Exception('SMS password recovery is not available. Use email delivery.');
            }

            $normalizedEmail = AuthenticationEmail::normalize($request->input('email'));
            $throttleKey = 'password-reset-link|'.$normalizedEmail.'|'.$request->ip();
            $throttleMax = 5;
            $throttleDecayMinutes = 60;

            if ($this->hasTooManyAttempts($throttleKey, $throttleMax)) {
                throw new \Exception('Too many password reset link requests. Please try again later.');
            }

            $user = AuthenticationEmail::resolveEligibleAccount($normalizedEmail);
            if ($user === null) {
                // Non-revealing: do not disclose whether the email exists or is ineligible.
                $this->incrementAttempts($throttleKey, $throttleDecayMinutes);

                throw new \Exception('If an eligible account exists for this email, a reset code has been sent.');
            }

            $otpLength = 6;
            $otpValidityMinutes = 10;
            if ($scope) {
                $otpValidityMinutes = (int) ($resolver->get('authentication.reset_password_token_life', $scope) ?: 10);
            }

            $otpInstance = app(Otp::class);
            $otpResponse = $otpInstance->generate($normalizedEmail, 'numeric', $otpLength, $otpValidityMinutes);
            $otpCode = is_object($otpResponse)
                ? (string) ($otpResponse->token ?? $otpResponse->otp ?? '')
                : (string) $otpResponse;

            if ($otpCode === '') {
                throw new \Exception('Unable to generate a reset code. Please try again.');
            }

            Notification::route('mail', $normalizedEmail)
                ->notify(new ResetPasswordNotification($otpCode, $user));

            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'school_id' => $school?->id,
                    'delivery_method' => 'email',
                    'target_email' => $normalizedEmail,
                ])
                ->log('Password reset link requested');

            $this->incrementAttempts($throttleKey, $throttleDecayMinutes);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'If an eligible account exists for this email, a reset code has been sent.'], 200);
            }

            return redirect()->back()->with('status', 'If an eligible account exists for this email, a reset code has been sent.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::warning('Password reset link request failed: '.$e->getMessage());
            if ($throttleKey !== null && ! str_contains($e->getMessage(), 'eligible account')) {
                $this->incrementAttempts($throttleKey, 60);
            }

            return $this->respondWithError($request, $e->getMessage());
        }
    }

    protected function hasTooManyAttempts(string $throttleKey, int $maxAttempts): bool
    {
        return app('Illuminate\Cache\RateLimiter')->tooManyAttempts($throttleKey, $maxAttempts);
    }

    /**
     * @param  int  $decayMinutes  Lockout window applied by RateLimiter::hit
     */
    protected function incrementAttempts(string $throttleKey, int $decayMinutes = 60): void
    {
        app('Illuminate\Cache\RateLimiter')->hit($throttleKey, $decayMinutes * 60);
    }

    protected function respondWithError(Request $request, string $message, int $statusCode = 400): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->withErrors(['email' => $message]);
    }
}
