<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\AcademicSessionService;
use Illuminate\Support\Facades\Redirect;

class EnsureCurrentSession
{
    public function handle($request, Closure $next)
    {
        if (! app(AcademicSessionService::class)->currentSession()) {
            return Redirect::route('academic.session.index')
                ->with('warning', 'Please select a current academic session first.');
        }
        return $next($request);
    }
}
