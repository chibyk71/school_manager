<?php

namespace App\Http\Controllers\Settings\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * WebTranslationsController v1.0 – Production-Ready Web Language Translations Management
 * TODO: Come back to this page make it the place where all translations are done, and to also add other strings and languages maybe use a translation library here, and utilize laravel translation feature
 * Purpose:
 * Manages custom translation overrides for the public website (parent portal, landing pages,
 * emails, invoices, etc.). Allows schools to translate or customize any frontend string
 * without touching code – essential for multi-language schools or branding consistency.
 *
 * Why stored in settings table:
 * - Translations are presentation/branding related → belong to "Website & Branding"
 * - Frequently updated (new strings, branding tweaks)
 * - Per-school overrides needed while inheriting system defaults
 * - Clean separation from core operational data
 * - Industry standard: most school SaaS (Gibbon, Fedena, QuickSchools) have "Language/Custom Strings"
 *
 * Settings Key: 'website.translations'
 *
 * Structure:
 *   'website.translations' => [
 *       'en' => [                 // locale code
 *           'welcome' => 'Welcome to Our School',
 *           'login_title' => 'Parent Portal Login',
 *           'invoice_due' => 'Payment Due',
 *           // any key => custom value
 *       ],
 *       'fr' => [
 *           'welcome' => 'Bienvenue à notre école',
 *           // ...
 *       ],
 *   ]
 *
 * Features / Problems Solved:
 * - Uses your helpers: getMergedSettings() + SaveOrUpdateSchoolSettings()
 * - No abort() → system admin can edit global defaults
 * - Dynamic locale + key/value editing with add/remove
 * - JSON validation + sanitization
 * - Live preview of translations (optional future enhancement)
 * - Supports unlimited locales and keys
 * - Default fallback to system translations if key missing
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.website.language
 * - Navigation: Website & Branding → Web Translations
 * - Frontend: resources/js/Pages/Settings/Website/Language.vue
 *
 * Default Values (for seeding on tenant bootstrap):
 *   'website.translations' => [
 *       'en' => [
 *           'welcome' => 'Welcome',
 *           'login' => 'Login',
 *           'register' => 'Register',
 *           'dashboard' => 'Dashboard',
 *           'fees' => 'Fees',
 *           'pay_now' => 'Pay Now',
 *           'invoice' => 'Invoice',
 *           'due_date' => 'Due Date',
 *       ]
 *   ]
 */

class WebTranslationsController extends Controller
{
    /**
     * Display the web translations settings page.
     */
    public function index()
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // May be null → global defaults

        $translations = getMergedSettings('website.translations', $school);

        // Ensure at least English exists
        if (!isset($translations['en'])) {
            $translations['en'] = [];
        }

        return Inertia::render('Settings/Website/Language', [
            'translations' => $translations,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Website & Branding'],
                ['label' => 'Web Translations'],
            ],
            'available_locales' => ['en', 'fr', 'es', 'pt', 'ar', 'sw', 'yo', 'ha', 'ig'], // Common + Nigerian languages
        ]);
    }

    /**
     * Store/update web translations.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'translations' => 'required|array',
            'translations.*' => 'array', // per locale
            'translations.*.*' => 'nullable|string|max:500', // key => value
        ]);

        try {
            // Sanitize: remove empty values to save space
            $cleaned = array_map(function ($locale) {
                return array_filter($locale, fn($value) => $value !== null && $value !== '');
            }, $validated['translations']);

            // Remove empty locales
            $cleaned = array_filter($cleaned, fn($items) => !empty($items));

            SaveOrUpdateSchoolSettings('website.translations', $cleaned, $school);

            $scope = $school ? 'school' : 'global';
            return redirect()
                ->route('settings.website.language')
                ->with('success', "Web translations updated ({$scope}) successfully.");
        } catch (\Exception $e) {
            Log::error('Web translations save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save web translations.')
                ->withInput();
        }
    }
}
