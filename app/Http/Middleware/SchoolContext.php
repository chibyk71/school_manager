<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve active school context from session, header, or request.
 * Does not read school_id from the Authentication Account (User) — that column does not exist.
 * School membership is a workspace concern, not authentication identity.
 */
class SchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $schoolId = $request->header('X-School-Id')
            ?: session('active_school_id')
            ?: $request->input('school_id');

        if ($schoolId) {
            $school = School::find($schoolId);

            if ($school) {
                app('schoolManager')->setActiveSchool($school);

                if (session('active_school_id') !== $school->id) {
                    session(['active_school_id' => $school->id]);
                }
            } else {
                $request->session()->forget('active_school_id');
            }
        }

        return $next($request);
    }
}
