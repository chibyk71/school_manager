<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing GDPR settings in a single-tenant school system.
 */
class GDPRController extends Controller
{
    /**
     * Display the GDPR settings.
     *
     * Retrieves GDPR settings for the active school and renders the view.
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

            $setting = getMergedSettings('gdpr', $school);

            return Inertia::render('Settings/School/GDPR', [
                'setting' => $setting,
            ], 'resources/js/Pages/Settings/School/GDPR.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch GDPR settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load GDPR settings.');
        }
    }

    /**
     * Store or update GDPR settings.
     *
     * Validates and saves GDPR settings for the active school.
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
                'content_text' => 'required|string|max:2000',
                'position' => 'required|string|in:top,bottom,left,right',
                'show_accept_button' => 'required|boolean',
                'accept_button_text' => 'required|string|max:100',
                'show_decline_button' => 'required|boolean',
                'decline_button_text' => 'required|string|max:100',
                'show_link' => 'required|boolean',
                'link_text' => 'required|string|max:100',
                'link_url' => 'required|url|max:255',
            ]);

            SaveOrUpdateSchoolSettings('gdpr', $validated, $school);

            return redirect()
                ->route('settings.gdpr.index')
                ->with('success', 'GDPR settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save GDPR settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save GDPR settings: ' . $e->getMessage());
        }
    }
}
