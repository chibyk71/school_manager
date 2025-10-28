<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laratrust\LaratrustFacade;

/**
 * Controller for verifying user email addresses with OTP in a multi-tenant school system.
 *
 * @group Authentication
 */
class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     *
     * Validates the OTP and marks the email as verified, or handles manual verification if allowed.
     *
     * @param Request $request The HTTP request instance.
     * @return RedirectResponse|JsonResponse
     *
     * @throws ValidationException If OTP validation fails.
     * @throws \Exception If school context or settings are invalid.
     */
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        try {
            // Ensure authenticated user
            $user = $request->user();
            if (!$user) {
                throw new \Exception('Unauthenticated user.');
            }

            // Check school context
            $school = GetSchoolModel();
            if (!$school || $user->school_id !== $school->id) {
                throw new \Exception('Invalid school context.');
            }

            // Check if email verification is required
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['enable_email_verification'] ?? false)) {
                Log::info("Email verification skipped for user {$user->id} in school {$school->id} as per settings.");
                return $this->respondWithSuccess($request, 'Email verification not required.', 'dashboard');
            }

            // Check if email is already verified
            if ($user->hasVerifiedEmail()) {
                Log::info("Email already verified for user {$user->id}.");
                return $this->respondWithSuccess($request, 'Email already verified.', 'dashboard');
            }

            // Check permission to verify email
            permitted('verify-email', $request->expectsJson());

            // Check rate limiting
            $throttleKey = 'verify-email|' . $user->id . '|' . $request->ip();
            $throttleMax = $authSettings['otp_verification_max_attempts'] ?? 5;
            $throttleLock = $authSettings['otp_validity'] ?? 10;
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax, $throttleLock)) {
                throw new \Exception('Too many verification attempts. Please try again later.');
            }

            // Validate request
            $validated = $request->validate([
                'otp' => ['required', 'string', 'size:' . ($authSettings['otp_length'] ?? 6)],
                'school_id' => ['required', 'exists:schools,id'],
            ]);

            // Validate OTP
            $otpInstance = new Otp();
            $response = $otpInstance->validate($user->email, $validated['otp']);

            if (!$response->status) {
                $this->incrementAttempts($throttleKey);
                throw ValidationException::withMessages(['otp' => 'Invalid or expired OTP.']);
            }

            // Check for manual verification fallback (admin-only)
            if ($request->has('manual_verification') && ($authSettings['allow_otp_fallback'] ?? false)) {
                if (!LaratrustFacade::hasRole('admin')) {
                    throw new \Exception('Manual verification is only allowed for admins.');
                }
                // Manual verification logic
                $targetUser = User::where('email', $request->target_email)
                    ->where('school_id', $school->id)
                    ->firstOrFail();
                $targetUser->markEmailAsVerified();
                activity()
                    ->performedOn($targetUser)
                    ->causedBy($user)
                    ->withProperties(['school_id' => $school->id, 'method' => 'manual'])
                    ->log('Email manually verified by admin');
                event(new Verified($targetUser));
                return $this->respondWithSuccess($request, 'Email manually verified.', 'dashboard');
            }

            // Mark email as verified
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school->id, 'method' => 'otp'])
                ->log('Email verified with OTP');

            // Clear rate limiter
            $this->clearAttempts($throttleKey);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Email verified successfully.'], 200);
            }

            return redirect()->intended(route('dashboard', absolute: false) . '?verified=1')
                ->with('status', 'Email verified successfully.');
        } catch (ValidationException $e) {
            Log::warning("Email verification validation failed for user ID {$user?->id}: {$e->getMessage()}");
            $this->incrementAttempts($throttleKey);
            throw $e;
        } catch (\Exception $e) {
            Log::error("Email verification failed for user ID {$user?->id}: {$e->getMessage()}");
            $this->incrementAttempts($throttleKey);
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
     * Clear attempts for rate limiting.
     *
     * @param string $throttleKey The throttle key.
     * @return void
     */
    protected function clearAttempts(string $throttleKey): void
    {
        app('Illuminate\Cache\RateLimiter')->clear($throttleKey);
    }

    /**
     * Respond with a success message for web or API requests.
     *
     * @param Request $request The HTTP request instance.
     * @param string $message The success message.
     * @param string|null $redirectRoute Optional redirect route name.
     * @return RedirectResponse|JsonResponse
     */
    protected function respondWithSuccess(Request $request, string $message, ?string $redirectRoute = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 200);
        }

        $response = redirect()->back()->with('status', $message);
        if ($redirectRoute) {
            $response = redirect()->intended(route($redirectRoute, absolute: false) . '?verified=1');
        }

        return $response;
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

        return redirect()->back()->withErrors(['otp' => $message]);
    }
}