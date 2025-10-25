<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;

class SetSchoolContext
{
    public function handle(Request $request, Closure $next)
    {
        $schoolService = app('schoolManager');
        $schoolId = $request->header('X-School-Id') ?? auth()->user()?->school_id;
        if ($schoolId) {
            $school = School::find($schoolId);
            if ($school) {
                $schoolService->setActiveSchool($school);
            }
        }
        return $next($request);
    }
}
