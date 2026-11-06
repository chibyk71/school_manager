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
 * authentication email.
 *
 * Public responses are intentionally identical for eligible, unknown, ineligible,
 * and ambiguous emails so callers cannot probe account eligibility. Only an
 * eligible account receives an OTP; rate limiting and internal logs still apply.
 *
 * @group Authentication
 */
class PasswordResetLinkController extends Controller
{
    private const GENERIC_STATUS = 'If an eligible account exists for this email, a reset code has been sent.';

    private const MAX_ATTEMPTS = 5;

    private const DECAY_MINUTES = 60;

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
     * Request a recovery OTP without revealing whether the email is eligible.
     *
     * Control flow:
     * 1. Validate input and reject enrollment-ID / SMS channels.
     * 2. If already locked out, return the generic success response without
     *    incrementing the counter again.
     * 3. Resolve at most one eligible account. If none, return generic success
     *    (still count the initiation attempt toward abuse limits).
     * 4. If eligible, generate OTP for the normalized email and notify that address.
     * 5. Always return the same HTTP status, structure, and message on the public path.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
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
                return $this->respondWithError($request, 'Password reset is not allowed.', 403);
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
                // Do not reveal enrollment resolution capability; same generic response shape.
                Log::info('Password recovery rejected: enrollment_id path is not supported in this phase.');

                return $this->genericInitiationResponse($request);
            }

            if ($request->input('delivery_method') === 'sms') {
                Log::info('Password recovery rejected: SMS delivery is disabled.');

                return $this->genericInitiationResponse($request);
            }

            $normalizedEmail = AuthenticationEmail::normalize($request->input('email'));
            $throttleKey = 'password-reset-link|'.$normalizedEmail.'|'.$request->ip();

            // Already locked: respond generically without further counter growth.
            if ($this->hasTooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
                Log::info('Password recovery initiation blocked by rate limit', ['key' => $throttleKey]);

                return $this->genericInitiationResponse($request);
            }

            $user = AuthenticationEmail::resolveEligibleAccount($normalizedEmail);

            if ($user === null) {
                Log::info('Password recovery initiation: no single eligible account', [
                    'email_hash' => hash('sha256', $normalizedEmail),
                ]);
                $this->incrementAttempts($throttleKey, self::DECAY_MINUTES);

                return $this->genericInitiationResponse($request);
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
                Log::error('Password recovery OTP generation returned empty token');
                $this->incrementAttempts($throttleKey, self::DECAY_MINUTES);

                return $this->genericInitiationResponse($request);
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

            $this->incrementAttempts($throttleKey, self::DECAY_MINUTES);

            return $this->genericInitiationResponse($request);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Password reset link request failed: '.$e->getMessage());

            // Infrastructure failures must not leak eligibility; still generic to the client.
            return $this->genericInitiationResponse($request);
        }
    }

    /**
     * Uniform client response for all initiation outcomes (except validation / hard policy denials).
     */
    protected function genericInitiationResponse(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => self::GENERIC_STATUS], 200);
        }

        return redirect()->back()->with('status', self::GENERIC_STATUS);
    }

    protected function hasTooManyAttempts(string $throttleKey, int $maxAttempts): bool
    {
        return app('Illuminate\Cache\RateLimiter')->tooManyAttempts($throttleKey, $maxAttempts);
    }

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
