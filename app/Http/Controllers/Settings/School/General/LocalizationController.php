<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocalizationController extends Controller
{
    public function index()
    {
        // Display Localization settings
        $setting = tenant()->getSetting('localization');
        return Inertia::render('Settings.School.Localization', compact('setting'));
    }

    public function store(Request $request)
    {
        // Save Localization settings
        $validatedData = $request->validate([
            'timezone' => 'required|string',
            'date_format' => 'required|string',
            'time_format' => 'required|string',
            'currency' => 'required|string',
            'currency_symbol' => 'required|string',
            'currency_position' => 'required|string',
            'decimal_separator' => 'required|string',
            'thousands_separator' => 'required|string',
            'language' => 'required|string',
        ]);

        tenant()->setSetting('localization', $validatedData);

        return redirect()->route('settings.school.localization.index')->with('success', 'Localization settings updated successfully.');
    }
    
    public function delete() {
        // Delete Localization settings
        GetSchoolModel()->deleteSetting('localization');
        return redirect()->route('settings.school.localization.index')->with('success', 'Localization settings deleted successfully.');
    }
}
