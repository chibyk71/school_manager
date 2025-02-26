<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\Tenant\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocalizationController extends Controller
{
    public function index()
    {
        // Display Localization settings
        $setting = getMergedSettings('localization', GetSchoolModel());
        return Inertia::render('Settings/School/Localization', compact('setting'));
    }

    public function store(Request $request)
    {
        // Save Localization settings
        $validatedData = $request->validate([
            'timezone' => 'required|string',
            'date_format' => 'required|string',
            'time_format' => 'required|string',
            'currency' => 'required|string',
            'currency_position' => 'required|string',
            'decimal_separator' => 'required|string',
            'thousands_separator' => 'required|string',
            'language' => 'required|string',
            'language_switcher' => 'sometimes|boolean',
            'financial_year' => 'required|date:year',
            'allowed_file_types' => 'required|array',
            'allowed_file_types.*' => 'required|string',
            'max_file_upload_size' => 'required|number'
        ]);

        GetSchoolModel()->setSetting('localization', $validatedData);

        return redirect()->route('website.localization')->with('success', 'Localization settings updated successfully.');
    }
}
