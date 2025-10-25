<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing SMS settings in a single-tenant school system.
 */
class SMSController extends Controller
{
    /**
     * Display the SMS settings.
     *
     * Retrieves SMS settings for the active school and renders the view.
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

            $setting = getMergedSettings('sms', $school);

            return Inertia::render('Settings/School/SMS', [
                'setting' => $setting,
            ], 'resources/js/Pages/Settings/School/SMS.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch SMS settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load SMS settings.');
        }
    }

    /**
     * Store or update SMS settings.
     *
     * Validates and saves SMS settings for the active school.
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
                'sms_provider' => 'required|string|in:termii,twilio,bulk_sms_nigeria',
                'sms_api_key' => 'required|string|max:255',
                'sms_sender_id' => 'nullable|string|max:50',
                'sms_enabled' => 'required|boolean',
            ]);

            SaveOrUpdateSchoolSettings('sms', $validated, $school);

            return redirect()
                ->route('settings.sms.index')
                ->with('success', 'SMS settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save SMS settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save SMS settings: ' . $e->getMessage());
        }
    }
}
