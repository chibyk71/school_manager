<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce Authentication Account eligibility on every authenticated request.
 *
 * Soft-deleted Persons (and accounts that fail canAuthenticate()) must not
 * continue an already-established session. Does not mutate AccountLifecycle.
 * Restricted forced-password-change and MFA routes remain available for
 * otherwise eligible accounts (handled by sibling middleware).
 */
class EnsureAccountEligible
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Force a fresh eligibility check (profile soft-delete may have occurred
        // after the session was established).
        $user->unsetRelation('profile');
        $user->load('profile');

        if ($user->canAuthenticate()) {
            return $next($request);
        }

        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'This account is no longer eligible to access the application.',
            ], 403);
        }

        return redirect()
            ->route('login')
            ->withErrors(['username' => 'This account is no longer eligible to access the application.']);
    }
}
