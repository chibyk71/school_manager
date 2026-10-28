<?php

namespace App\Http\Middleware;

use App\Facades\Academic;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

/**
 * Require a current academic session for operational app use.
 *
 * Intentionally skipped for institution setup (schools), session administration,
 * and unauthenticated flows — those must work before any session is active.
 */
class EnsureCurrentSession
{
    /**
     * Path prefixes that must not require a current academic session.
     *
     * @var list<string>
     */
    protected array $except = [
        'login',
        'logout',
        'register',
        'password',
        'forgot-password',
        'reset-password',
        'email',
        'verify-email',
        'confirm-password',
        'two-factor',
        'schools',
        'settings/academic-sessions',
        'settings/session',
        'settings/terms',
        'up',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if (Academic::currentSession()) {
            return $next($request);
        }

        // Canonical route name (settings academic-sessions resource).
        if (Route::has('academic-sessions.index')) {
            return Redirect::route('academic-sessions.index')
                ->with('warning', 'Please select a current academic session first.');
        }

        // Legacy / incomplete route catalogues must not 500 the request.
        if (Route::has('academic.session.index')) {
            return Redirect::route('academic.session.index')
                ->with('warning', 'Please select a current academic session first.');
        }

        return $next($request);
    }

    protected function shouldSkip(Request $request): bool
    {
        if (! $request->user()) {
            return true;
        }

        $path = ltrim($request->path(), '/');

        foreach ($this->except as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
