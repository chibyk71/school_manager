<?php

namespace App\Http\Controllers\Settings\School\Email;

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

        return Inertia::render('Settings/System/Email', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'mail_driver' => 'required|string|max:255',
            'from_email' => 'required|email',
            'from_name' => 'required|string|max:255',
            'mail_host' => 'required|string|max:255'
        ]);

        // Save or update the school-specific settings
        GetSchoolModel()->setSetting('email.'.$validatedData['mail_driver'], $validatedData);

        return redirect()
            ->route('settings.email.general.index')
            ->with('success', 'Email settings updated successfully.');
    }
}
