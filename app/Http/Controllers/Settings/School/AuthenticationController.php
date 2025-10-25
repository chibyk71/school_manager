<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing authentication settings in a single-tenant school system.
 */
class AuthenticationController extends Controller
{
    /**
     * Display the authentication settings.
     *
     * Retrieves authentication settings for the active school and renders the view.
     *
     * @return \Inertia\Response The Inertia response with settings data.
     *
     * @throws \Exception If settings retrieval fails or no active school is found.
     */
    public function index()
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $setting = getMergedSettings('authentication', $school);

            return Inertia::render('Settings/School/Authentication', [
                'setting' => $setting,
            ], 'resources/js/Pages/Settings/School/Authentication.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch authentication settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load authentication settings.');
        }
    }

    /**
     * Store or update authentication settings.
     *
     * Validates and saves authentication settings for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings storage fails.
     */
    public function store(Request $request)
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validate([
                'login_throttle_max' => 'required|integer|min:1',
                'login_throttle_lock' => 'required|integer|min:1',
                'reset_password_token_life' => 'required|integer|min:1',
                'allow_password_reset' => 'required|boolean',
                'enable_email_verification' => 'required|boolean',
                'allow_user_registration' => 'required|boolean',
                'account_approval' => 'required|boolean',
                'oAuth_registration' => 'required|boolean',
                'show_terms_on_registration' => 'required|boolean',
            ]);

            SaveOrUpdateSchoolSettings('authentication', $validated, $school);

            return redirect()
                ->route('settings.authentication.index')
                ->with('success', 'Authentication settings saved successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save authentication settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save authentication settings: ' . $e->getMessage());
        }
    }
}
