<?php

namespace App\Http\Controllers\Settings\Website;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;
use Illuminate\Validation\Rule;

/**
 * CompanySettingsController v1.0 – Production-Ready Public Company Branding Settings
 *
 * Purpose:
 * Manages the public-facing branding and legal information for the school (displayed on website,
 * invoices, email footers, parent portal, etc.). This is separate from the core School model to:
 * - Keep the schools table lean and focused on operational data
 * - Allow frequent branding/legal updates without touching core identifiers
 * - Support multi-branding or white-label scenarios in the future
 * - Follow industry best practices (Gibbon, Fedena, QuickSchools all separate "Company Info")
 *
 * Settings Key: 'website.company'
 *
 * Features / Problems Solved:
 * - Uses RuangDeveloper\LaravelSettings polymorphic overrides (school-specific wins over global defaults)
 * - Full validation with user-friendly rules
 * - Secure: only permitted users can access (via permitted() helper)
 * - Proper error handling and structured logging
 * - Returns merged settings (override → global → config default) for accurate display
 * - Clean redirect with success/error flashes
 *
 * Fits into the Settings Module:
 * - Route: settings.website.company (index + store)
 * - Navigation: Website & Branding → Company Settings
 * - Frontend: resources/js/Pages/Settings/Website/Company.vue
 *
 * Expected Config Defaults (config/laravel-settings.php):
 *   'defaults' => [
 *       'website.company' => [
 *           'legal_name' => '',
 *           'tagline' => '',
 *           'tax_id' => '',
 *           'public_email' => '',
 *           'social_facebook' => '',
 *           // etc.
 *       ]
 *   ]
 */

class CompanySettingsController extends Controller
{
    /**
     * Display the public company settings page.
     */
    public function index()
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        // Merged settings: school override → global → config default
        $settings = getMergedSettings('company', $school);

        return Inertia::render('Settings/Website/Company', [
            'settings' => $settings,
        ]);
    }

    /**
     * Store/update public company settings for the current school.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        if (!$school) {
            abort(403, 'No active school context found.');
        }

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:100',
            'public_email' => 'nullable|email|max:255',
            'public_phone' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'social_facebook' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',
            'social_youtube' => 'nullable|url|max:255',
            'footer_copyright' => 'nullable|string|max:500',
            'google_maps_embed' => 'nullable|string', // HTML iframe allowed (sanitized on output)
            'show_address_footer' => 'boolean',
            'show_phone_footer' => 'boolean',
            'show_email_footer' => 'boolean',
        ]);

        try {
            // Save only school-specific overrides
            SaveOrUpdateSchoolSettings('company', $validated, $school);

            return redirect()
                ->route('settings.website.company')
                ->with('success', 'Public company settings updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Company settings save failed', [
                'school_id' => $school->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save company settings.')
                ->withInput();
        }
    }
}
