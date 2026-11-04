<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laragear\TwoFactor\Facades\Auth2FA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks unrestricted workspace access until MFA challenge succeeds for the session.
 * Accounts without MFA enabled pass through.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'hasTwoFactorAuth') || ! $user->hasTwoFactorAuth()) {
            return $next($request);
        }

        if (class_exists(Auth2FA::class) && Auth2FA::hasSafeDeviceCookie($request)) {
            return $next($request);
        }

        if ($request->session()->get('auth.2fa_confirmed_at')) {
            return $next($request);
        }

        if ($request->routeIs([
            '2fa.challenge',
            '2fa.verify',
            '2fa.enable',
            '2fa.confirm',
            '2fa.disable',
            'logout',
            'password.force.edit',
            'password.force.update',
        ])) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Two-factor authentication required.',
                'two_factor_required' => true,
            ], 403);
        }

        return redirect()->route('2fa.challenge');
    }
}
