<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks unrestricted workspace access until MFA challenge succeeds for the session.
 *
 * Accounts without MFA enabled pass through. When MFA is enabled, access is allowed
 * only if the session has completed the challenge (auth.2fa_confirmed_at) or — when
 * two-factor.safe_devices.enabled — the request presents a valid safe device via
 * User::isSafeDevice(). The Auth2FA facade does not provide safe-device cookie helpers.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'hasTwoFactorEnabled') || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('auth.2fa_confirmed_at')) {
            return $next($request);
        }

        // Safe-device bypass only when explicitly enabled in package config.
        if ((bool) config('two-factor.safe_devices.enabled', false)
            && method_exists($user, 'isSafeDevice')
            && $user->isSafeDevice($request)) {
            // Mark session confirmed so subsequent requests skip re-check cost.
            $request->session()->put('auth.2fa_confirmed_at', time());

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
