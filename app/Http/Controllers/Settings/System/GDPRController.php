<?php

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GDPRController extends Controller
{
    public function index()
    {
        // Display GDPR settings
        $setting = getMergedSettings('gdpr', GetSchoolModel()); // Fetch GDPR settings from the database
        return Inertia::render('Settings/System/GDPRCookies', compact('setting'));
    }

    public function store(Request $request)
    {
        // Save GDPR settings
        $validated = $request->validate([
            'content_text' => 'required|string',
            'position' => 'required|string',
            'show_accept_button' => 'required|boolean',
            'accept_button_text' => 'required|string',
            'show_decline_button' => 'required|boolean',
            'decline_button_text' => 'required|string',
            'show_link' => 'required|boolean',
            'link_text' => 'required|string',
            'link_url' => 'required|string',
        ]);

        // Get the current school
        // Update the settings with the new values from the request
        SaveOrUpdateSchoolSettings('gdpr', $validated);

        return redirect()->route('system.gdpr')->with('success', 'GDPR settings updated successfully.');
    }
}
