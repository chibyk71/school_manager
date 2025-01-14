<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SchoolIdentification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Attempt to get the active school ID from the session
        $schoolId = session('active_school_id');

        // If no school ID in the session, check if it's in the request
        if (!$schoolId && $request->has('school_id')) {
            $schoolId = $request->get('school_id');
            session(['active_school_id' => $schoolId]);
        }

        // If a school ID is found, load the school and bind it to the service container
        if ($schoolId) {
            $school = School::find($schoolId);
            if ($school) {
                app('schoolManager')->setActiveSchool($school);
            }
        }
 
        return $next($request);
    }
}
