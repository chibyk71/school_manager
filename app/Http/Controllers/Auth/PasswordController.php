<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Controller for updating user passwords in a multi-tenant school system.
 *
 * @group Authentication
 */
class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * Validates the current password and updates to a new password, respecting tenant settings.
     *
     * @param Request $request The HTTP request instance.
     * @return RedirectResponse|JsonResponse
     *
     * @throws ValidationException If validation fails.
     * @throws \Exception If school context is invalid or password change is not allowed.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
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

            // Check permission to update password
            permitted('update-password', $request->expectsJson());

            // Check if password change is allowed
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['allow_password_change'] ?? true)) {
                Log::info("Password change disabled for user {$user->id} in school {$school->id} as per settings.");
                throw new \Exception('Password change is not allowed.');
            }

            // Check rate limiting
            $throttleKey = 'password-update|' . $user->id . '|' . $request->ip();
            $throttleMax = $authSettings['password_update_max_attempts'] ?? 5;
            $throttleLock = $authSettings['password_update_lock_minutes'] ?? 1;
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax, $throttleLock)) {
                throw new \Exception('Too many password update attempts. Please try again later.');
            }

            // Build password validation rules based on settings
            $passwordRules = Password::min($authSettings['password_min_length'] ?? 8);
            if ($authSettings['password_require_letters'] ?? true) {
                $passwordRules = $passwordRules->letters();
            }
            if ($authSettings['password_require_mixed_case'] ?? false) {
                $passwordRules = $passwordRules->mixedCase();
            }
            if ($authSettings['password_require_numbers'] ?? true) {
                $passwordRules = $passwordRules->numbers();
            }
            if ($authSettings['password_require_symbols'] ?? false) {
                $passwordRules = $passwordRules->symbols();
            }

            // Validate request
            $validated = $request->validate([
                'current_password' => ['required', 'current_password'],
                'password' => ['required', 'confirmed', $passwordRules],
                'school_id' => ['required', 'exists:schools,id'],
            ]);

            // Update password
            $user->update([
                'password' => Hash::make($validated['password']),
                'must_change_password'=> false,
            ]);

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school->id])
                ->log('Password updated successfully');

            // Clear rate limiter
            $this->clearAttempts($throttleKey);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password updated successfully.'], 200);
            }

            return redirect()->back()->with('status', 'Password updated successfully.');
        } catch (ValidationException $e) {
            Log::warning("Password update validation failed for user ID {$user?->id}: {$e->getMessage()}");
            $this->incrementAttempts($throttleKey);
            throw $e;
        } catch (\Exception $e) {
            Log::error("Password update failed for user ID {$user?->id}: {$e->getMessage()}");
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

        return redirect()->back()->withErrors(['password' => $message]);
    }
}