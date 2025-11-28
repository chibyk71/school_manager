<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for handling password reset requests in a multi-tenant school system.
 *
 * @group Authentication
 */
class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     *
     * @param Request $request The HTTP request instance.
     * @return InertiaResponse|JsonResponse
     */
    public function create(Request $request)
    {
        try {
            // Check school context
            $school = GetSchoolModel();

            // Check if password reset is allowed
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['allow_password_reset'] ?? false)) {
                Log::info("Password reset disabled for school {$school->id} as per settings.");
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
            Log::error("Failed to display password reset view: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load password reset page.');
        }
    }

    /**
     * Handle an incoming new password request.
     *
     * @param ResetPasswordRequest $request The validated HTTP request.
     * @return RedirectResponse|JsonResponse
     */
    public function store(ResetPasswordRequest $request): RedirectResponse|JsonResponse
    {
        try {
            // Check school context
            $school = GetSchoolModel();

            // Check if password reset is allowed
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['allow_password_reset'] ?? false)) {
                throw new \Exception('Password reset is not allowed.');
            }

            // Check rate limiting
            $throttleKey = 'password-reset|' . $request->email . '|' . $request->ip();
            $throttleMax = 5; // Hardcoded, can be moved to settings
            $throttleLock = $authSettings['reset_password_token_life'] ?? 60;
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax, $throttleLock)) {
                throw new \Exception('Too many password reset attempts. Please try again later.');
            }

            // Validate OTP and find user
            $otpInstance = new Otp();
            $response = $otpInstance->validate($request->email, $request->token);

            if (!$response->status) {
                $this->incrementAttempts($throttleKey);
                throw new \Exception('Invalid or expired OTP.');
            }

            $user = User::where('email', $request->email)
                ->where('school_id', $school?->id)
                ->firstOrFail();

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school?->id])
                ->log('Password reset successfully');

            // Fire PasswordReset event
            event(new PasswordReset($user));

            // Clear rate limiter
            $this->clearAttempts($throttleKey);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password reset successfully.'], 200);
            }

            return redirect()->route('login')
                ->with('status', 'Password reset successfully.');
        } catch (\Exception $e) {
            Log::error("Password reset failed for email {$request->email}: {$e->getMessage()}");
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

        return redirect()->back()->withErrors(['token' => $message]);
    }
}