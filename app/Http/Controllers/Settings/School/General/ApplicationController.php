<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class ApplicationController extends Controller
{
    public function index()
    {
        // Fetch tenant-wide settings as defaults
        $tenantSettings = Settings::get('general', []);

        // Fetch school-specific settings
        // TODO find a way to get the current school id
        $schoolSettings = School::getSetting('general', []);

        // Merge settings: Use school-specific if set, otherwise tenant defaults
        $settings = array_replace_recursive($tenantSettings, array_filter($schoolSettings, fn($value) => $value !== null));


        return Inertia::render('Settings.School.General', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'school_name' => 'required|string|max:255',
            'short_name' => 'required|string|max:255',
            'motto' => 'sometimes|string',
            'about' => 'sometimes|string',
            'pledge' => 'nullable|string',
            'anthem' => 'nullable|string',
            'sidebar_default'=> 'required|string|in:mini,full,compactǀ',
            'table_pagination'=> 'required|integer',
            'outside_click'=> 'required|boolean',
            'start_day_of_week'=> 'required|integer|between:0,6',
            'session_from'=> 'required|date',
            'session_to'=> 'required|date'
        ]);

        // Save or update the school-specific settings
        tenant()->setSetting('general', $validatedData);

        return redirect()
            ->route('settings.school.general.index')
            ->with('success', 'General settings updated successfully.');
    }
}
