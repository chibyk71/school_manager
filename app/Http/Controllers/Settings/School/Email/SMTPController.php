<?php

namespace App\Http\Controllers\Settings\School\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class SMTPController extends Controller
{
    public function index()
    {
        // Merge settings: Use school-specific if set, otherwise tenant defaults
        $settings = getMergedSettings('smtp', GetSchoolModel());

        return Inertia::render('Settings.Email.SMTP', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'smtpHost' => 'required|string|max:255',
            'smtpPort' => 'required|integer',
            'smtpUser' => 'required|string|max:255',
            'smtpPassword' => 'required|string|max:255',
            'smtpFromEmail' => 'required|email'
        ]);

        // Save or update the school-specific settings
        SaveOrUpdateSchoolSettings('smtp', $validatedData);

        return redirect()
            ->route('settings.email.smtp.index')
            ->with('success', 'SMTP settings updated successfully.');
    }
}