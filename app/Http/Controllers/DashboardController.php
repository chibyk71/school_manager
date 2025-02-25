<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // TODO check the authenticated users role and redirect to the appropriate dashboard
        return Inertia::render('Dashboard');
    }
}
