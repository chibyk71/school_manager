<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for confirming user passwords before sensitive actions in a multi-tenant school system.
 *
 * @group Authentication
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Display the confirm password view.
     *
     * @param Request $request The HTTP request instance.
     * @return InertiaResponse|JsonResponse
     */
    public function show(Request $request): InertiaResponse|JsonResponse
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

            // Check permission to access password confirmation
            permitted('confirm-password', $request->expectsJson());

            // Check if password confirmation is required
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['require_password_confirmation'] ?? false)) {
                Log::info("Password confirmation skipped for user {$user->id} in school {$school->id} as per settings.");
                return $this->respondWithSuccess($request, 'Password confirmation not required.', 'dashboard');
            }

            // Check if password is already confirmed
            if ($request->session()->has('auth.password_confirmed_at') &&
                (time() - $request->session()->get('auth.password_confirmed_at') < ($authSettings['password_confirmation_ttl'] ?? 3600))) {
                Log::info("Password already confirmed for user {$user->id}.");
                return $this->respondWithSuccess($request, 'Password already confirmed.', 'dashboard');
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password confirmation required'], 200);
            }

            return Inertia::render('Auth/ConfirmPassword', [
                'school_id' => $school->id,
                'user' => $user->only('id', 'name', 'email'),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to show password confirmation view for user ID {$user?->id}: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load password confirmation page.');
        }
    }

    /**
     * Confirm the user's password.
     *
     * @param ConfirmPasswordRequest $request The validated HTTP request.
     * @return RedirectResponse|JsonResponse
     */
    public function store(ConfirmPasswordRequest $request): RedirectResponse|JsonResponse
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

            // Check permission to confirm password
            permitted('confirm-password', $request->expectsJson());

            // Check rate limiting
            $throttleKey = 'confirm-password|' . $user->id . '|' . $request->ip();
            $throttleMax = 5; // Hardcoded, can be moved to settings if needed
            $throttleLock = 1; // 1 minute lockout
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax, $throttleLock)) {
                throw new \Exception('Too many password confirmation attempts. Please try again later.');
            }

            // Validate password via ConfirmPasswordRequest
            $request->validatePassword();

            // Store confirmation timestamp
            $request->session()->put('auth.password_confirmed_at', time());

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school->id])
                ->log('Password confirmed');

            // Clear rate limiter
            $this->clearAttempts($throttleKey);

            return $this->respondWithSuccess($request, 'Password confirmed successfully.', 'dashboard');
        } catch (\Exception $e) {
            Log::error("Password confirmation failed for user ID {$user?->id}: {$e->getMessage()}");
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

        return redirect()->back()->withErrors(['password' => $message]);
    }
}
