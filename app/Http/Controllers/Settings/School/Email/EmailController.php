<?php

namespace App\Http\Controllers\Settings\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class EmailController extends Controller
{
    public function index()
    {
        // Merge settings: Use school-specific if set, otherwise tenant defaults
        $settings = getMergedSettings('email', GetSchoolModel());

        return Inertia::render('Settings.Email.General', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'from_email' => 'required|email',
            'from_name' => 'required|string|max:255',
            'mail_host' => 'required|string|max:255'
        ]);

        // Save or update the school-specific settings
        GetSchoolModel()->setSetting('email', $validatedData);

        return redirect()
            ->route('settings.email.general.index')
            ->with('success', 'Email settings updated successfully.');
    }
}