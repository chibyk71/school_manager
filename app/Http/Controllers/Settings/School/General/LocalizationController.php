<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing localization settings in a single-tenant school system.
 */
class LocalizationController extends Controller
{
    /**
     * Display the localization settings.
     *
     * Retrieves localization settings for the active school and renders the view.
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

            $setting = getMergedSettings('localization', $school);

            return Inertia::render('Settings/School/Localization', [
                'setting' => $setting,
            ], 'resources/js/Pages/Settings/School/Localization.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch localization settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load localization settings.');
        }
    }

    /**
     * Store or update localization settings.
     *
     * Validates and saves localization settings for the active school.
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
                'timezone' => 'required|string|timezone',
                'date_format' => 'required|string|max:50',
                'time_format' => 'required|string|max:50',
                'currency' => 'required|string|max:10',
                'currency_position' => 'required|string|in:before,after',
                'decimal_separator' => 'required|string|in:.,',
                'thousands_separator' => 'required|string|in:,,., ',
                'language' => 'required|string|max:10',
                'language_switcher' => 'boolean',
                'financial_year' => 'required|integer|min:2000|max:' . (date('Y') + 10),
                'allowed_file_types' => 'required|array',
                'allowed_file_types.*' => 'required|string|max:50',
                'max_file_upload_size' => 'required|numeric|min:1|max:10240', // 10MB max
            ]);

            SaveOrUpdateSchoolSettings('localization', $validated, $school);

            return redirect()
                ->route('settings.localization.index')
                ->with('success', 'Localization settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save localization settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save localization settings: ' . $e->getMessage());
        }
    }
}
