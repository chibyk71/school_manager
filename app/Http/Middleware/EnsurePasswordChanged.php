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

        if ($user === null || ! $user->requiresPasswordChange()) {
            return $next($request);
        }

        if ($request->routeIs([
            'password.force.edit',
            'password.force.update',
            'logout',
            'two-factor.*',
            '2fa.*',
            'verification.*',
        ])) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password change required before continuing.',
                'force_password_change' => true,
            ], 403);
        }

        return redirect()->route('password.force.edit');
    }
}
