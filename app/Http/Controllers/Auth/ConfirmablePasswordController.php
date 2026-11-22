<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Confirm password before sensitive actions.
 *
 * Account-scoped self-service: any authenticated eligible account may confirm
 * its own password. No Laratrust permission is required.
 *
 * @group Authentication
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Display the confirm password view.
     *
     * @return InertiaResponse|JsonResponse|RedirectResponse
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user) {
                throw new \Exception('Unauthenticated user.');
            }

            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;

            $authSettings = function_exists('getMergedSettings')
                ? getMergedSettings('authentication', $school)
                : [];

            if (
                $request->session()->has('auth.password_confirmed_at')
                && (time() - $request->session()->get('auth.password_confirmed_at')
                    < (int) ($authSettings['password_confirmation_ttl'] ?? config('auth.password_timeout', 10800)))
            ) {
                Log::info("Password already confirmed for user {$user->id}.");

                return $this->respondWithSuccess($request, 'Password already confirmed.', 'dashboard');
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password confirmation required'], 200);
            }

            return Inertia::render('Auth/ConfirmPassword', [
                'school_id' => $school?->id,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show password confirmation view: '.$e->getMessage());

            return $this->respondWithError($request, 'Failed to load password confirmation page.');
        }
    }

    /**
     * Confirm the user's password.
     */
    public function store(ConfirmPasswordRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        // Always defined so catch can rate-limit without "Undefined variable $throttleKey".
        $throttleKey = 'confirm-password|'.($user?->id ?? 'guest').'|'.$request->ip();

        try {
            if (! $user) {
                throw new \Exception('Unauthenticated user.');
            }

            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            if ($request->filled('school_id') && $school && $school->id !== $request->integer('school_id')) {
                throw new \Exception('Invalid school context.');
            }

            // Self-service: authenticated account confirms its own credential.
            // Do not gate on Laratrust "confirm-password" permission.

            $throttleMax = 5;
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax)) {
                throw ValidationException::withMessages([
                    'password' => 'Too many password confirmation attempts. Please try again later.',
                ]);
            }

            // Throws ValidationException on mismatch — rethrown below (not swallowed).
            $request->validatePassword();

            $request->session()->put('auth.password_confirmed_at', time());

            if (function_exists('activity')) {
                activity()
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties(['school_id' => $school?->id])
                    ->log('Password confirmed');
            }

            $this->clearAttempts($throttleKey);

            return $this->respondWithSuccess($request, 'Password confirmed successfully.', 'dashboard');
        } catch (ValidationException $e) {
            $this->incrementAttempts($throttleKey);

            throw $e;
        } catch (\Exception $e) {
            Log::error("Password confirmation failed for user ID {$user?->id}: {$e->getMessage()}");
            $this->incrementAttempts($throttleKey);

            return $this->respondWithError($request, $e->getMessage());
        }
    }

    protected function hasTooManyAttempts(string $throttleKey, int $maxAttempts): bool
    {
        return app('Illuminate\Cache\RateLimiter')->tooManyAttempts($throttleKey, $maxAttempts);
    }

    protected function incrementAttempts(string $throttleKey): void
    {
        app('Illuminate\Cache\RateLimiter')->hit($throttleKey);
    }

    protected function clearAttempts(string $throttleKey): void
    {
        app('Illuminate\Cache\RateLimiter')->clear($throttleKey);
    }

    protected function respondWithSuccess(Request $request, string $message, ?string $redirectRoute = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 200);
        }

        if ($redirectRoute && Route::has($redirectRoute)) {
            return redirect()->intended(route($redirectRoute, absolute: false))->with('status', $message);
        }

        return redirect()->back()->with('status', $message);
    }

    protected function respondWithError(Request $request, string $message, int $statusCode = 400): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->withErrors(['password' => $message]);
    }
}
