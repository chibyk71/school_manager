<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Controller for handling user registration in a multi-tenant school system.
 *
 * @group Authentication
 */
class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @param Request $request The HTTP request instance.
     * @return InertiaResponse|JsonResponse
     */
    public function create(Request $request): InertiaResponse|JsonResponse
    {
        try {
            // Check school context
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Check if registration is allowed
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['allow_user_registration'] ?? false)) {
                Log::info("User registration disabled for school {$school->id} as per settings.");
                return $this->respondWithError($request, 'User registration is not allowed.', 403);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'school_id' => $school->id,
                    'status' => session('status'),
                    'allow_user_registration' => $authSettings['allow_user_registration'],
                ], 200);
            }

            return Inertia::render('Auth/Register', [
                'school_id' => $school->id,
                'status' => session('status'),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to display registration view: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load registration page.');
        }
    }

    /**
     * Handle an incoming registration request.
     *
     * Creates a new user, assigns a default role, and logs them in.
     *
     * @param Request $request The HTTP request instance.
     * @return RedirectResponse|JsonResponse
     *
     * @throws ValidationException If validation fails.
     * @throws \Exception If registration is not allowed or role assignment fails.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        try {
            // Check school context
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Check permission (optional for registration)
            permitted('register', $request->expectsJson());

            // Check if registration is allowed
            $authSettings = getMergedSettings('authentication', $school);
            if (!($authSettings['allow_user_registration'] ?? false)) {
                throw new \Exception('User registration is not allowed.');
            }

            // Check rate limiting
            $throttleKey = 'register|' . $request->ip();
            $throttleMax = $authSettings['registration_max_attempts'] ?? 5;
            $throttleLock = $authSettings['registration_lock_minutes'] ?? 1;
            if ($this->hasTooManyAttempts($throttleKey, $throttleMax, $throttleLock)) {
                throw new \Exception('Too many registration attempts. Please try again later.');
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
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,NULL,id,school_id,' . $school->id],
                'password' => ['required', 'confirmed', $passwordRules],
                'school_id' => ['required', 'exists:schools,id'],
            ]);

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'school_id' => $school->id,
            ]);

            // Assign default role
            $defaultRole = $authSettings['default_registration_role'] ?? 'guardian';
            if (!$user->hasRole($defaultRole, $school?->id)) {
                $user->addRole($defaultRole, $school->id);
            }

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school->id, 'role' => $defaultRole])
                ->log('User registered');

            // Fire Registered event
            event(new Registered($user));

            // Log in the user
            Auth::login($user);

            // Clear rate limiter
            $this->clearAttempts($throttleKey);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Registration successful.',
                    'user' => $user->only('id', 'name', 'email', 'school_id'),
                ], 200);
            }

            return redirect()->intended(route('dashboard', absolute: false))
                ->with('status', 'Registration successful.');
        } catch (ValidationException $e) {
            Log::warning("Registration validation failed: {$e->getMessage()}");
            $this->incrementAttempts($throttleKey);
            throw $e;
        } catch (\Exception $e) {
            Log::error("Registration failed for email {$request->email}: {$e->getMessage()}");
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

        return redirect()->back()->withErrors(['email' => $message]);
    }
}