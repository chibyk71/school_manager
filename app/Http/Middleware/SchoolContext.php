<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Auth\SchoolWorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve active school workspace context for authenticated users only when authorized.
 *
 * Guests may receive a non-persisted, request-scoped school for public branding/settings.
 * Authenticated users may only bind a School workspace via valid school_users participation.
 * Explicit unauthorized selections are rejected (403) and prior unauthorized context is cleared.
 *
 * Authentication itself does not require School membership.
 */
class SchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $explicitId = $request->header('X-School-Id') ?: $request->input('school_id');
        $sessionId = session('active_school_id');
        $schoolId = $explicitId ?: $sessionId;

        if (! $schoolId) {
            return $next($request);
        }

        $school = School::find($schoolId);

        if (! $school) {
            $this->clearWorkspace($request);

            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            // Guest: in-memory only — do not persist into session.
            app('schoolManager')->setActiveSchool($school, persistSession: false);

            return $next($request);
        }

        if (! SchoolWorkspaceAccess::canSelect($user, $school)) {
            $this->clearWorkspace($request);

            // Explicit client selection (header/input) → forbidden.
            // Stale session alone → clear and continue without that workspace.
            if ($explicitId && (string) $explicitId === (string) $schoolId) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'You are not authorized to select this school workspace.',
                    ], 403);
                }

                abort(403, 'You are not authorized to select this school workspace.');
            }

            return $next($request);
        }

        app('schoolManager')->setActiveSchool($school, persistSession: true);

        if (session('active_school_id') !== $school->id) {
            session(['active_school_id' => $school->id]);
        }

        return $next($request);
    }

    protected function clearWorkspace(Request $request): void
    {
        $request->session()->forget('active_school_id');
        try {
            $manager = app('schoolManager');
            if (method_exists($manager, 'clearActiveSchool')) {
                $manager->clearActiveSchool();
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
