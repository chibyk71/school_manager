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
    /**
     * Display the login view.
     *
     * @param Request $request The HTTP request instance.
     * @return InertiaResponse|JsonResponse
     */
    public function create(Request $request): InertiaResponse|JsonResponse
    {
        try {
            // Get active school
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Retrieve authentication settings
            $authSettings = getMergedSettings('authentication', $school);

            if ($request->expectsJson()) {
                return response()->json([
                    'canResetPassword' => $authSettings['allow_password_reset'] && Route::has('password.request'),
                    'status' => session('status'),
                    'school_id' => $school->id,
                ], 200);
            }

            return Inertia::render('Auth/Login', [
                'canResetPassword' => $authSettings['allow_password_reset'] && Route::has('password.request'),
                'status' => session('status'),
                'school_id' => $school->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to display login view: {$e->getMessage()}");
            return $this->respondWithError($request, 'Failed to load login page.');
        }
    }

    /**
     * Handle an incoming authentication request.
     *
     * Authenticates users via email or enrollment_id, respecting tenant settings and school context.
     *
     * @param LoginRequest $request The validated HTTP request.
     * @return RedirectResponse|JsonResponse
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        try {
            // Get active school
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Check permission (optional for login)
            permitted('login', $request->expectsJson());

            // Authenticate via LoginRequest
            $request->authenticate();

            // Get authenticated user
            $user = Auth::user();
            if ($user->school_id !== $school->id) {
                Auth::logout();
                throw new \Exception('User not associated with this school.');
            }

            // Retrieve settings
            $authSettings = getMergedSettings('authentication', $school);

            // Log successful login
            $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'enrollment_id';
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['school_id' => $school->id, 'login_field' => $loginField])
                ->log('User logged in');

            // Check if password confirmation is required for sensitive routes
            $intendedUrl = redirect()->intended(route('dashboard', absolute: false))->getTargetUrl();
            if (($authSettings['require_password_confirmation'] ?? false) &&
                str_contains($intendedUrl, '/dashboard') &&
                !$request->session()->has('auth.password_confirmed_at')) {
                return $this->respondWithSuccess($request, 'Login successful, please confirm password.', 'password.confirm');
            }

            if ($request->expectsJson()) {
                $token = $user->createToken('auth-token')->plainTextToken;
                return response()->json([
                    'message' => 'Login successful.',
                    'token' => $token,
                    'user' => $user->only('id', 'name', 'email', 'enrollment_id', 'roles'),
                ], 200);
            }

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            Log::error("Login failed: {$e->getMessage()}");
            return $this->respondWithError($request, $e->getMessage());
        }
    }

    /**
     * Destroy an authenticated session.
     *
     * @param Request $request The HTTP request instance.
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $user = Auth::user();
            $school = GetSchoolModel();

            if ($user && $school) {
                // Log logout
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

        $response = redirect()->back()->with('success', $message);
        if ($redirectRoute) {
            $response = redirect()->route($redirectRoute);
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

        return redirect()->back()->withErrors(['login' => $message]);
    }
}
