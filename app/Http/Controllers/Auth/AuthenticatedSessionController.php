<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Session login/logout. Authentication is independent of school membership and roles.
 * Workspace/school selection is a subsequent concern after a valid session exists.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): InertiaResponse|JsonResponse|RedirectResponse
    {
        try {
            if ($request->user()) {
                return redirect()->intended(route('dashboard', absolute: false));
            }

            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            $authSettings = function_exists('getMergedSettings')
                ? getMergedSettings('authentication', $school)
                : [];

            // Public registration is disabled in Phase 1 regardless of settings.
            $canRegister = false;

            if ($request->expectsJson()) {
                return response()->json([
                    'canResetPassword' => ($authSettings['allow_password_reset'] ?? true) && Route::has('password.request'),
                    'canRegister' => $canRegister,
                    'status' => session('status'),
                ]);
            }

            return Inertia::render('Auth/Login', [
                'canResetPassword' => ($authSettings['allow_password_reset'] ?? true) && Route::has('password.request'),
                'canRegister' => $canRegister,
                'status' => session('status'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show login: '.$e->getMessage());

            return $this->respondWithError($request, 'Failed to load login page.');
        }
    }

    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $request->authenticate();

            // Session fixation protection
            $request->session()->regenerate();
            // New session must re-confirm MFA
            $request->session()->forget('auth.2fa_confirmed_at');

            $user = Auth::user();

            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'school_id' => function_exists('GetSchoolModel') ? GetSchoolModel()?->id : null,
                    'force_password_change' => $user->requiresPasswordChange(),
                ])
                ->log('User logged in');

            if ($user->requiresPasswordChange()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Password change required before continuing.',
                        'force_password_change' => true,
                        'redirect' => route('password.force.edit'),
                    ], 200);
                }

                return redirect()->route('password.force.edit');
            }

            if (method_exists($user, 'hasTwoFactorAuth') && $user->hasTwoFactorAuth()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Two-factor authentication required.',
                        'two_factor_required' => true,
                        'redirect' => route('2fa.challenge'),
                    ], 200);
                }

                return redirect()->route('2fa.challenge');
            }

            // Optionally seed active school from membership if none is selected yet.
            // This is workspace convenience only — never a gate on authentication.
            $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
            if ($school === null) {
                $first = $user->schools()->first();
                if ($first) {
                    app('schoolManager')->setActiveSchool($first);
                    session(['active_school_id' => $first->id]);
                }
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Login successful.',
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'full_name' => $user->full_name,
                    ],
                ]);
            }

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Login failed: '.$e->getMessage());
            Auth::logout();

            return $this->respondWithError($request, $e->getMessage());
        }
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Logged out.']);
        }

        return redirect('/');
    }

    protected function respondWithError(Request $request, string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->back()->withErrors(['login' => $message]);
    }
}
