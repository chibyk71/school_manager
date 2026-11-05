<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Auth\SchoolWorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve active school workspace context.
 *
 * Guests may receive a request-scoped school for public branding/settings only;
 * the selection is not treated as authorized participation.
 *
 * Authenticated users may only bind a School workspace when they have valid
 * participation in that School or explicit Tenant-wide authority. Unauthorized
 * selections are rejected and any persisted unauthorized context is cleared.
 *
 * Authentication itself does not require School membership.
 */
class SchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $schoolId = $request->header('X-School-Id')
            ?: session('active_school_id')
            ?: $request->input('school_id');

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
            // Guest: in-memory context only for public pages; do not elevate session privilege.
            app('schoolManager')->setActiveSchool($school);

            return $next($request);
        }

        if (! SchoolWorkspaceAccess::canSelect($user, $school)) {
            $this->clearWorkspace($request);

            return $next($request);
        }

        app('schoolManager')->setActiveSchool($school);

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
            } elseif (method_exists($manager, 'setActiveSchool')) {
                // Best-effort: leave manager without unauthorized school.
                $ref = new \ReflectionClass($manager);
                if ($ref->hasProperty('activeSchool')) {
                    $prop = $ref->getProperty('activeSchool');
                    $prop->setAccessible(true);
                    $prop->setValue($manager, null);
                }
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
