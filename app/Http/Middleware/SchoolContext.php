<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SchoolContext
{
    /**
     * Handle an incoming request and set the active school context.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Initialize schoolId to null
        $schoolId = null;

        // 2. Prioritize: Authenticated User (Most reliable source for a logged-in user)
        $schoolId = auth()->user()?->school_id;

        // 3. Secondary: Explicit Header (Common for API/AJAX requests)
        if (!$schoolId) {
            $schoolId = $request->header('X-School-Id');
        }

        // 4. Tertiary: Session or Request Parameter (Common for Web selection/routing)
        if (!$schoolId) {
            $schoolId = session('active_school_id');
        }

        // 5. Lowest Priority: Check for explicit ID in the query string (e.g., during school selection)
        if (!$schoolId) {
            $schoolId = $request->input('school_id');
        }

        // ----------------------------------------------------------------------
        // Set Context and Store in Session if we found a valid ID
        // ----------------------------------------------------------------------
        if ($schoolId) {
            $school = School::find($schoolId);

            if ($school) {
                // Set the active school instance on the service container
                app('schoolManager')->setActiveSchool($school);

                // Ensure the context is stored in the session for subsequent requests
                // that might not carry the user/header/query parameter.
                if (session('active_school_id') !== $school->id) {
                    session(['active_school_id' => $school->id]);
                }
            } else {
                // Optional: Clear session if the ID was invalid/deleted
                $request->session()->forget('active_school_id');
            }
        }

        return $next($request);
    }
}
