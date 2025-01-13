<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class ApplicationController extends Controller
{
    public function index()
    {
        // Merge settings: Use school-specific if set, otherwise tenant defaults
        $settings = getMergedSettings('application', GetSchoolModel());
        return Inertia::render('Settings.School.Application', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'app_name' => 'required|string|max:255',
            'short_name' => 'required|string|max:50',
            'sidebar_default' => 'required|string|in:full,compact,mini',
            'table_pagination' => 'required|integer|min:1|max:100',
            'outside_click' => 'required|boolean',
            'allow_school_custom_logo' => 'required|boolean',
            'allow_school_default_payment' => 'required|boolean'
        ]);

        // Save or update the school-specific settings
        SaveOrUpdateSchoolSettings('general', $validatedData);

        return redirect()
            ->route('settings.school.general.index')
            ->with('success', 'General settings updated successfully.');
    }
}
