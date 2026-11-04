<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserManagement\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Restricted first-login / forced password-change flow.
 * Available without school membership or roles.
 */
class ForcedPasswordChangeController extends Controller
{
    public function edit(Request $request): InertiaResponse|JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->requiresPasswordChange()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'force_password_change' => true,
                'username' => $user->username,
            ]);
        }

        return Inertia::render('Auth/ForcePasswordChange', [
            'username' => $user->username,
        ]);
    }

    public function update(Request $request, UserAccountService $accounts): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            throw ValidationException::withMessages(['password' => 'Unauthenticated.']);
        }

        if (! $user->requiresPasswordChange()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No password change required.'])
                : redirect()->intended(route('dashboard', absolute: false));
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'force_password_change' => false,
        ])->save();

        // Keep lifecycle Active; only clear the force flag.
        $accounts->setForcePasswordChange($user->fresh(), false);

        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Password updated.']);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
