<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Controller for handling password reset link requests in a multi-tenant school system.
 *
 * @group Authentication
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * @param Request $request The HTTP request instance.
     * @return InertiaResponse|JsonResponse
     */
    public function create(Request $request)
    {
        try {
            // Check school context
            $school = GetSchoolModel();

            // Check if password reset is allowed (Phase 1 settings capability)
            $resolver = app(SettingsResolver::class);
            $scope = SettingsScope::school((string) $school->id);
            $allowPasswordReset = $resolver->get('authentication.allow_password_reset', $scope);
            if (! $allowPasswordReset) {
                Log::info("Password reset disabled for school {$school->id} as per settings.");
                return $this->respondWithError($request, 'Password reset is not allowed.', 403);
            }

            // Check SMS settings (legacy path — not in Phase 1 pilot)
            $smsSettings = getMergedSettings('sms', $school);
            $smsEnabled = $smsSettings['sms_enabled'] ?? false;

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => session('status'),
                    'school_id' => $school?->id,
                    'sms_enabled' => $smsEnabled,
                ], 200);
            }

            return Inertia::render('Auth/ForgotPassword', [
                'status' => session('status'),
                'school_id' => $school?->id,
                'sms_enabled' => $smsEnabled,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to display password reset link view: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load password reset page.');
        }
    }

    /**
     * Handle an incoming password reset link request.
     *
     * Sends a password reset OTP via email or SMS based on tenant settings and user input.
     *
     * @param Request $request The HTTP request instance.
     * @return RedirectResponse|JsonResponse
     *
     * @throws ValidationException If validation fails.
     * @throws \Exception If user lookup or OTP generation fails.
     */
    public function store(Request $request)
    {
        // Established only once rate limiting applies; catch must not hit an unset key.
        $throttleKey = null;

        try {
            // Check school context
            $school = GetSchoolModel();

            // Check if password reset is allowed (Phase 1 settings capability)
            $resolver = app(SettingsResolver::class);
            $scope = SettingsScope::school((string) $school->id);
            $allowPasswordReset = $resolver->get('authentication.allow_password_reset', $scope);
            if (! $allowPasswordReset) {
                throw new \Exception('Password reset is not allowed.');
            }

            // Legacy authentication bag for non-pilot keys only
            $authSettings = getMergedSettings('authentication', $school);

            // Check SMS settings (legacy path — not in Phase 1 pilot)
            $smsSettings = getMergedSettings('sms', $school);
            $smsEnabled = $smsSettings['sms_enabled'] ?? false;

            // Validate request
            $request->validate([
                'email' => ['required_without:enrollment_id', 'email', 'nullable'],
                'enrollment_id' => ['required_without:email', 'string', 'nullable'],
                'phone_number' => ['required_if:delivery_method,sms', 'string', 'nullable'],
                'delivery_method' => ['required', 'string', 'in:email,sms'],
                'school_id' => ['required', 'exists:schools,id'],
            ], [
                'email.required_without' => 'Email is required if enrollment ID is not provided.',
                'enrollment_id.required_without' => 'Enrollment ID is required if email is not provided.',
                'phone_number.required_if' => 'Phone number is required for SMS delivery.',
            ]);

            // Check rate limiting (OTP lifetime is not a rate-limit lock duration)
            $throttleKey = 'password-reset-link|' . ($request->email ?: $request->enrollment_id) . '|' . $request->ip();
            $throttleMax = $authSettings['password_reset_max_attempts'] ?? 5;
            // hasTooManyAttempts currently ignores lock minutes; keep a fixed default
            // rather than coupling to authentication.reset_password_token_life.
            $throttleLock = 60;
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax, $throttleLock)) {
                throw new \Exception('Too many password reset link requests. Please try again later.');
            }

            // Find user
            $user = null;
            $targetEmail = $request->email;
            if ($request->filled('enrollment_id')) {
                // Phase 1: enrollment_id is not an Authentication Account identifier.
                // Student identity resolution belongs to the academic domain, not users table.
                throw new \Exception('Password reset by enrollment ID is not supported in this phase. Use the account email.');
            }

            // Shared communication emails: require exactly one linked eligible Person.
            $normalized = strtolower((string) $request->email);
            $matches = \App\Models\Profile::query()
                ->whereRaw('LOWER(email) = ?', [$normalized])
                ->whereNotNull('user_id')
                ->whereNull('deleted_at')
                ->with('user')
                ->get()
                ->filter(fn ($profile) => $profile->user !== null)
                ->values();

            if ($matches->count() === 0) {
                throw new \Exception('No user found with the provided details.');
            }
            if ($matches->count() > 1) {
                throw new \Exception(
                    'Multiple accounts share this email. Sign in with your username, then use the authenticated password-change flow, or contact an administrator.'
                );
            }

            $user = $matches->first()->user;
            $targetEmail = $request->email;

            if (! $user) {
                throw new \Exception('No user found with the provided details.');
            }

            // Check SMS delivery
            if ($request->delivery_method === 'sms' && !$smsEnabled) {
                throw new \Exception('SMS delivery is not enabled for this school.');
            }

            // Generate OTP (lifetime from Phase 1 settings capability only)
            $otpInstance = app(Otp::class);
            $otpLength = $authSettings['otp_length'] ?? 6;
            $otpValidity = $resolver->get('authentication.reset_password_token_life', $scope);
            $response = $otpInstance->generate($targetEmail, 'numeric', $otpLength, $otpValidity);

            if (!$response->status) {
                Log::error("Failed to generate OTP for email {$targetEmail}: {$response->message}");
                throw new \Exception('Failed to generate OTP.');
            }

            // Send notification
            $notification = new ResetPasswordNotification($response->otp, $user);
            if ($request->delivery_method === 'sms' && $smsEnabled) {
                Notification::route('sms', $request->phone_number)->notify($notification);
            } else {
                Notification::route('mail', $targetEmail)->notify($notification);
            }

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'school_id' => $school->id,
                    'delivery_method' => $request->delivery_method,
                    'target_email' => $targetEmail,
                ])
                ->log('Password reset link requested');

            // Increment rate limiter
            $this->incrementAttempts($throttleKey);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password reset link sent successfully.'], 200);
            }

            return redirect()->back()->with('status', 'Password reset link sent successfully.');
        } catch (ValidationException $e) {
            Log::warning("Password reset link validation failed: {$e->getMessage()}");
            throw $e;
        } catch (\Exception $e) {
            Log::error("Password reset link request failed for email/enrollment {$request->email}/{$request->enrollment_id}: {$e->getMessage()}");
            if ($throttleKey !== null) {
                $this->incrementAttempts($throttleKey);
            }

            return $this->respondWithError($request, $e->getMessage());
        }
    }

    /**
     * Check if there are too many attempts.
     *
     * @param string $throttleKey The throttle key.
     * @param int $maxAttempts Maximum allowed attempts.
     * @param int $lockMinutes Lockout duration in minutes.
     * @return bool
     */
    protected function hasTooManyAttempts(string $throttleKey, int $maxAttempts, int $lockMinutes): bool
    {
        return app('Illuminate\Cache\RateLimiter')->tooManyAttempts($throttleKey, $maxAttempts);
    }

    /**
     * Increment attempts for rate limiting.
     *
     * @param string $throttleKey The throttle key.
     * @return void
     */
    protected function incrementAttempts(string $throttleKey): void
    {
        app('Illuminate\Cache\RateLimiter')->hit($throttleKey);
    }

    /**
     * Respond with an error message for web or API requests.
     *
     * @param Request $request The HTTP request instance.
     * @param string $message The error message.
     * @param int $statusCode The HTTP status code.
     * @return RedirectResponse|JsonResponse
     */
    protected function respondWithError(Request $request, string $message, int $statusCode = 400): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->withErrors(['email' => $message]);
    }
}
