<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend enforcement: Active accounts with force_password_change must complete
 * the restricted password-change flow before accessing normal workspace routes.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->requiresPasswordChange()) {
            return $next($request);
        }

        // Allow the password-change endpoints and logout only.
        if ($request->routeIs([
            'password.edit',
            'password.update',
            'password.change',
            'password.change.update',
            'logout',
            'two-factor.*',
        ])) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password change required before continuing.',
                'force_password_change' => true,
            ], 403);
        }

        return redirect()->route('password.edit');
    }
}
