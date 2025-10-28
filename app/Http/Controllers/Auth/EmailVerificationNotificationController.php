<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailVerificationRequest;
use App\Notifications\EmailVerificationNotification;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Controller for sending email verification notifications with OTP in a multi-tenant school system.
 *
 * @group Authentication
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification with OTP.
     *
     * @param EmailVerificationRequest $request The validated HTTP request.
     * @return RedirectResponse|JsonResponse
     */
    public function store(EmailVerificationRequest $request): RedirectResponse|JsonResponse
    {
        try {
            // Ensure authenticated user
            $user = $request->user();
            if (!$user) {
                throw new \Exception('Unauthenticated user.');
            }

            // Check school context
            $school = GetSchoolModel();
            if (!$school || $school->id !== $request->school_id || $user->school_id !== $school->id) {
                throw new \Exception('Invalid school context.');
            }

            // Check permission to request verification
            permitted('request-email-verification', $request->expectsJson());

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

            // Check rate limiting
            $throttleKey = 'email-verification|' . $user->id . '|' . $request->ip();
            $throttleMax = 5; // Hardcoded, can be moved to settings
            $throttleLock = $authSettings['otp_validity'] ?? 10;
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax, $throttleLock)) {
                throw new \Exception('Too many OTP requests. Please try again later.');
            }

            // Generate OTP
            $otpInstance = new Otp();
            $otpLength = $authSettings['otp_length'] ?? 6;
            $otpValidity = $authSettings['otp_validity'] ?? 10;
            $response = $otpInstance->generate($user->email, 'numeric', $otpLength, $otpValidity);

            if (!$response->status) {
                Log::error("Failed to generate OTP for user {$user->id}: {$response->message}");
                throw new \Exception('Failed to generate OTP.');
            }

            $otp = $response->otp;

            // Send email verification notification
            Notification::send($user, new EmailVerificationNotification($user, $otp));

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school->id, 'otp_length' => $otpLength])
                ->log('Email verification OTP sent');

            // Increment rate limiter
            $this->incrementAttempts($throttleKey);

            return $this->respondWithSuccess($request, 'Verification OTP sent to your email.', 'email.verify');
        } catch (\Exception $e) {
            Log::error("Email verification notification failed for user ID {$user?->id}: {$e->getMessage()}");
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
            $response = redirect()->intended(route($redirectRoute, absolute: false));
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
