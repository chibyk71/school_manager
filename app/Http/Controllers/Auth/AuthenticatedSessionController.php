<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing authenticated sessions in a multi-tenant school system.
 *
 * Handles login, logout, and session management with tenant-specific settings.
 *
 * @group Authentication
 */
class AuthenticatedSessionController extends Controller
{
    /* --------------------------------------------------------------------- */
    /*  PUBLIC ENDPOINTS                                                     */
    /* --------------------------------------------------------------------- */

    /**
     * Display the login view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function create(Request $request): InertiaResponse|JsonResponse|RedirectResponse
    {
        try {
            // -----------------------------------------------------------------
            // No school context required for the login page – system-wide
            // admins must be able to see the login form even when no school
            // is selected.
            // -----------------------------------------------------------------
            $school = GetSchoolModel(); // may be null

            $authSettings = getMergedSettings('authentication', $school);

            if ($request->expectsJson()) {
                return response()->json([
                    'canResetPassword' => $authSettings['allow_password_reset'] && Route::has('password.request'),
                    'canRegister' => $authSettings['allow_user_registration'] && Route::has('register'),
                    'status' => session('status'),
                    'school_id' => $school?->id,
                ], 200);
            }

            return Inertia::render('Auth/Login', [
                'canResetPassword' => $authSettings['allow_password_reset'] && Route::has('password.request'),
                'canRegister' => $authSettings['allow_user_registration'] && Route::has('register'),
                'status' => session('status'),
                'school_id' => $school?->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to display login view: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load login page.');
        }
    }

    /**
     * Handle an incoming authentication request.
     *
     * Authenticates users via email or enrollment_id.
     * - Regular users **must** belong to the currently selected school.
     * - System-wide admins **do not** need a school and can manage every school.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        try {
            // -----------------------------------------------------------------
            // 1. Authenticate the credentials (LoginRequest does the heavy lifting)
            // -----------------------------------------------------------------
            $request->authenticate();
            $user = Auth::user();
            $school = GetSchoolModel(); // may be null

            // -----------------------------------------------------------------
            // 2. Determine if the user is a *system-wide* admin
            // -----------------------------------------------------------------
            $isSystemAdmin = $this->isSystemWideAdmin($user);

            Log::debug("User ID {$user->id} is " . ($isSystemAdmin ? '' : 'not ') . "a system-wide admin.");

            // -----------------------------------------------------------------
            // 3. Enforce school-membership rule **only for non-system admins**
            // -----------------------------------------------------------------
            if (!($isSystemAdmin && $school)) {
                // Normal user – must be attached to the selected school
                if (!$user->schools()->where('school_id', $school->id)->exists()) {
                    Auth::logout();
                    throw new \Exception('User not associated with this school.');
                }
            }

            // -----------------------------------------------------------------
            // 4. Retrieve authentication settings (fallback to system defaults)
            // -----------------------------------------------------------------
            $authSettings = getMergedSettings('authentication', $school);

            // -----------------------------------------------------------------
            // 5. Activity log – always record the login
            // -----------------------------------------------------------------
            $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'enrollment_id';
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'school_id' => $school?->id,
                    'login_field' => $loginField,
                    'system_admin' => $isSystemAdmin,
                ])
                ->log('User logged in');

            // -----------------------------------------------------------------
            // 6. Password-confirmation check (only for sensitive routes)
            // -----------------------------------------------------------------
            $intendedUrl = redirect()->intended(route('dashboard', absolute: false))->getTargetUrl();

            if (
                ($authSettings['require_password_confirmation'] ?? false) &&
                str_contains($intendedUrl, '/dashboard') &&
                !$request->session()->has('auth.password_confirmed_at')
            ) {
                return $this->respondWithSuccess(
                    $request,
                    'Login successful, please confirm password.',
                    'password.confirm'
                );
            }
            
            // -----------------------------------------------------------------
            // 7. API response (SPA / mobile)
            // -----------------------------------------------------------------
            if ($request->expectsJson()) {
                $token = $user->createToken('auth-token')->plainTextToken;

                return response()->json([
                    'message' => 'Login successful.',
                    'token' => $token,
                    'user' => $user->only('id', 'name', 'email', 'enrollment_id', 'roles'),
                ], 200);
            }

            // -----------------------------------------------------------------
            // 8. Web redirect
            // -----------------------------------------------------------------
            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            Log::error("Login failed: {$e->getMessage()}");
            return $this->respondWithError($request, $e->getMessage());
        }
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $user = Auth::user();
            $school = GetSchoolModel();

            if ($user && $school) {
                activity()
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties(['school_id' => $school->id])
                    ->log('User logged out');
            }

            if ($request->expectsJson()) {
                if ($user) {
                    $user->tokens()->delete();
                }
                return response()->json(['message' => 'Logged out successfully.'], 200);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/');
        } catch (\Exception $e) {
            Log::error("Logout failed: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to log out.');
        }
    }

    /* --------------------------------------------------------------------- */
    /*  PRIVATE HELPERS                                                      */
    /* --------------------------------------------------------------------- */

    /**
     * Determine whether the given user is a system-wide admin.
     *
     * System-wide admins are **not** attached to any school (i.e. the pivot
     * table `school_user` has no rows for them).  You may extend this logic
     * with a dedicated `is_system_admin` column or a role check if needed.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    private function isSystemWideAdmin(User $user): bool
    {
        // Option 1: No school association at all
        if ($user->schools()->count() === 0 || $user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Respond with a success message for web or API requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $message
     * @param  string|null  $redirectRoute
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function respondWithSuccess(
        Request $request,
        string $message,
        ?string $redirectRoute = null
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 200);
        }

        $response = redirect()->back()->with('success', $message);
        if ($redirectRoute) {
            $response = redirect()->route($redirectRoute);
        }

        return $response;
    }

    /**
     * Respond with an error message for web or API requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $message
     * @param  int  $statusCode
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function respondWithError(
        Request $request,
        string $message,
        int $statusCode = 400
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->withErrors(['login' => $message]);
    }
}
