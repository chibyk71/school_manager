<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Prompt to verify Person primary email for the authenticated account.
 */
class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|InertiaResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->auth_email_verified_at !== null) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $email = $user->profile?->email;

        if ($request->expectsJson()) {
            return response()->json([
                'email' => $email,
                'verified' => false,
            ]);
        }

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $email,
            'status' => session('status'),
        ]);
    }
}
