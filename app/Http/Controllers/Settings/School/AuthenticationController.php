<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthenticationController extends Controller
{
    public function index()
    {
        $setting = getMergedSettings('authentication', GetSchoolModel());
        // Display Contact settings
        return Inertia::render('Settings.School.Authentication', compact('setting'));
    }

    public function store(Request $request)
    {
        // Save Contact settings
        $validated = $request->validate([
            'login_throttle_max' => 'required|integer|min:1',
            'login_throttle_lock' => 'required|integer|min:1',
            'reset_password_token_life' => 'required|integer|min:1',
            'allow_password_reset' => 'required|boolean',
            'enable_email_verification' => 'required|boolean',
            'allow_user_registration' => 'required|boolean',
            'account_approvel' => 'required|boolean',
            'oAuth_registration' => 'required|boolean',
            'show_terms_on_registration' => 'required|boolean'
        ]);

        // Save the settings to the database or perform other actions
       SaveOrUpdateSchoolSettings('authentication', $validated);


        return redirect()->route('settings.school.authentication.index')->with('success', 'Authentication settings saved successfully.');
    }
}
