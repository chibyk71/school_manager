<?php

namespace App\Http\Controllers\Settings\Others;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class MaintainanceController extends Controller
{
    public function index()
    {
        // Merge settings: Use school-specific if set, otherwise tenant defaults
        $settings = getMergedSettings('maintenance', GetSchoolModel());

        return Inertia::render('Settings/Others/Maintainance', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'maintenance_mode' => 'required|string',
            'maintenance_key' => 'required|string',
            'maintenance_mode_url' => 'required|string'
        ]);

        // Save or update the school-specific settings
        SaveOrUpdateSchoolSettings('maintenance', $validatedData);

        return redirect()
            ->route('settings.school.maintenance.index')
            ->with('success', 'Maintenance settings updated successfully.');
    }
}
