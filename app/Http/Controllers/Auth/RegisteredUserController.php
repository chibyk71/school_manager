<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\EmailVerificationOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Handles user registration in a multi-tenant school system.
 *
 * @group Authentication
 */
class RegisteredUserController extends Controller
{

    /** --------------------------------------------------------------------- */
    /**  PUBLIC ENDPOINTS                                                     */
    /** --------------------------------------------------------------------- */

   /**
     * Display the registration view.
     *
     * @param Request $request The HTTP request instance.
     * @return InertiaResponse|JsonResponse
     */
    public function create(Request $request)
    {
        try {
             // Check school context
            $school = GetSchoolModel();               //

            $auth   = getMergedSettings('authentication', $school);

            // Registration disabled globally or per-school?
            if (! ($auth['allow_user_registration'] ?? false)) {
                return $this->respondWithError($request, 'Registration is disabled.', 403);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'school_id'               => $school?->id,
                    'allow_user_registration' => true,
                    'show_terms'              => $auth['show_terms_on_registration'] ?? false,
                    'schools'                 => \App\Models\School::pluck('name', 'id') ?? collect(),
                ]);
            }

            return Inertia::render('Auth/Register', [
                'school_id'  => $school?->id,
                'show_terms' => $auth['show_terms_on_registration'] ?? false,
                'schools'    => \App\Models\School::orderBy('name')->get(['id', 'name'])->toArray() ?? collect(),
            ]);
        } catch (\Exception $e) {
            Log::error("Register view error: {$e->getMessage()}");
            return $this->respondWithError($request, 'Unable to load registration page.');
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
            $school = GetSchoolModel(); // null → system admin registration
            $auth   = getMergedSettings('authentication', $school);

            // -----------------------------------------------------------------
            // 1. Global / per-school registration guard
            // -----------------------------------------------------------------
            if (! ($auth['allow_user_registration'] ?? false)) {
                throw new \Exception('Registration is not allowed.');
            }

            // -----------------------------------------------------------------
            // 2. Rate limiting (IP + school)
            // -----------------------------------------------------------------
            $throttleKey = $this->throttleKey($request, $school);
            $maxAttempts = (int) ($auth['registration_max_attempts'] ?? 5);
            $lockMinutes = (int) ($auth['registration_lock_minutes'] ?? 1);

            if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                throw new \Exception(trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]));
            }

            // -----------------------------------------------------------------
            // 3. Dynamic password rules
            // -----------------------------------------------------------------
            $pwd = Password::min($auth['password_min_length'] ?? 8);
            if ($auth['password_require_letters'] ?? true)   $pwd = $pwd->letters();
            if ($auth['password_require_mixed_case'] ?? false) $pwd = $pwd->mixedCase();
            if ($auth['password_require_numbers'] ?? true)   $pwd = $pwd->numbers();
            if ($auth['password_require_symbols'] ?? false) $pwd = $pwd->symbols();

            // -----------------------------------------------------------------
            // 4. Validation
            // -----------------------------------------------------------------
            $rules = [
                'name'                  => ['required', 'string', 'max:255'],
                'email'                 => [
                    'required', 'email', 'max:255',
                    'unique:users,email,NULL'],
                'password'              => ['required', 'confirmed', $pwd],
                'school_id'             => ['nullable', 'exists:schools,id'],
                'terms'                 => ($auth['show_terms_on_registration'] ?? false)
                    ? ['required', 'accepted']
                    : ['nullable'],
            ];

            $validated = $request->validate($rules);

            // -----------------------------------------------------------------
            // 5. Create user (inactive if approval required)
            // -----------------------------------------------------------------
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'school_id' => $school?->id ?? $validated['school_id'],
                'active'    => ! ($auth['account_approval'] ?? false),
            ]);

            // -----------------------------------------------------------------
            // 6. Assign default role
            // -----------------------------------------------------------------
            $defaultRole = $auth['default_registration_role'] ?? 'guardian';
            $user->addRole($defaultRole, $user->school_id);

            // -----------------------------------------------------------------
            // 7. Activity log
            // -----------------------------------------------------------------
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'school_id' => $user->school_id,
                    'role'      => $defaultRole,
                    'needs_approval' => $auth['account_approval'] ?? false,
                ])
                ->log('User registered');

            // -----------------------------------------------------------------
            // 8. Account approval flow
            // -----------------------------------------------------------------
            if ($auth['account_approval'] ?? false) {
                $user->notify(new AccountCreatedNotification($user));
                $msg = 'Your account has been created and is awaiting admin approval.';
            } else {
                $msg = 'Registration successful.';
            }

            // -----------------------------------------------------------------
            // 9. Email verification (optional)
            // -----------------------------------------------------------------
            if ($auth['enable_email_verification'] ?? false) {
                $otp = app(\Ichtrojan\Otp\Otp::class)->generate($user->email, $auth['otp_length'] ?? 6, $auth['otp_validity'] ?? 10);
                $user->notify(new EmailVerificationNotification($user, $otp->token));
                $msg .= ' Please check your email for the verification code.';
            }

            // -----------------------------------------------------------------
            // 10. Login (only if NOT awaiting approval)
            // -----------------------------------------------------------------
            if (! ($auth['account_approval'] ?? false)) {
                Auth::login($user);
            }

            // -----------------------------------------------------------------
            // 11. Clear throttle & respond
            // -----------------------------------------------------------------
            RateLimiter::clear($throttleKey);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $msg,
                    'user'    => $user->only('id', 'name', 'email', 'school_id', 'active'),
                ], 201);
            }

            return redirect()->intended(route('dashboard'))
                ->with('status', $msg);
        } catch (ValidationException $e) {
            RateLimiter::hit($this->throttleKey($request, $school));
            throw $e;
        } catch (\Exception $e) {
            RateLimiter::hit($this->throttleKey($request, $school));
            Log::error("Registration failed: {$e->getMessage()}");
            return $this->respondWithError($request, $e->getMessage());
        }
    }

    /** --------------------------------------------------------------------- */
    /**  PRIVATE HELPERS                                                      */
    /** --------------------------------------------------------------------- */

    private function throttleKey(Request $request, $school): string
    {
        $schoolId = $school?->id ?? 'global';
        return 'register|' . $request->ip() . '|' . $schoolId;
    }

    protected function respondWithError(
        Request $request,
        string $message,
        int $status = 400
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $status);
        }
        return redirect()->back()->withErrors(['email' => $message]);
    }
}
