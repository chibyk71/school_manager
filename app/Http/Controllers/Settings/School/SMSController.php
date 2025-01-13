<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SMSController extends Controller
{
    public function index()
    {
        // Display Payments settings
        $setting = getMergedSettings('sms', GetSchoolModel()); // Fetch SMS settings from the database
        return Inertia::render('Settings.School.SMS', compact('setting'));
    }

    public function store(Request $request)
    {
        // Save SMS settings
        $validated = $request->validate([
            'sms_provider' => 'required|string',
            'sms_api_key' => 'required|string',
            // Add more validation rules as needed
        ]);

        // Get the current school
        // Update the settings with the new values from the request
        SaveOrUpdateSchoolSettings('sms', $validated);

        return redirect()->route('settings.school.sms.index')->with('success', 'SMS settings updated successfully.');
    }
}
