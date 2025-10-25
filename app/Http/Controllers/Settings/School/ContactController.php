<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing contact settings in a single-tenant school system.
 */
class ContactController extends Controller
{
    /**
     * Display the contact settings.
     *
     * Retrieves contact settings for the active school and renders the view.
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

            $setting = getMergedSettings('contact', $school);

            return Inertia::render('Settings/School/Contact', [
                'setting' => $setting,
            ], 'resources/js/Pages/Settings/School/Contact.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch contact settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load contact settings.');
        }
    }

    /**
     * Store or update contact settings.
     *
     * Validates and saves contact settings for the active school.
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
                'phone' => 'required|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
                'email' => 'required|email|max:255',
                'facebook' => 'nullable|url|max:255',
                'twitter' => 'nullable|url|max:255',
                'instagram' => 'nullable|url|max:255',
                'linkedin' => 'nullable|url|max:255',
                'youtube' => 'nullable|url|max:255',
                'map_embed_code' => 'nullable|string|max:1000|regex:/^<iframe.*<\/iframe>$/',
            ]);

            SaveOrUpdateSchoolSettings('contact', $validated, $school);

            return redirect()
                ->route('settings.contact.index')
                ->with('success', 'Contact settings saved successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save contact settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save contact settings: ' . $e->getMessage());
        }
    }
}
