<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * AcademicSessionSettingsController v1.0 – Production-Ready Academic Session Configuration
 *
 * Central configuration page for school-wide academic session settings and policies.
 * Allows each school (tenant) to customize how the system handles academic sessions,
 * terms, activation, closure, and related behaviors.
 *
 * Why this page is essential:
 * - Schools have vastly different preferences (e.g. 3 terms vs 2 semesters, auto-activate next term, require principal approval for closure)
 * - Enables tenant-specific customization without hard-coding
 * - Affects core calendar logic (activation, closure, date rules, promotion triggers)
 * - Industry standard: most robust school systems have a dedicated "Academic Calendar Settings" page
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → platform defaults + school overrides
 * - Full validation with realistic ranges and booleans for toggles
 * - Clean grouped form structure (PrimeVue Tabs or Fieldsets recommended)
 * - Responsive, accessible UI-ready (Inertia + PrimeVue)
 * - Production-ready: security (permission check), error handling, structured logging
 * - Future-ready: easy to add more settings (e.g. promotion rules, report card templates)
 *
 * Settings Key: 'academic.session_rules'
 *
 * Recommended Structure (stored as JSON in school_settings table):
 *   'academic.session_rules' => [
 *       'default_term_structure' => 'three_terms',      // three_terms | two_semesters | custom
 *       'auto_activate_next_term' => true,              // Automatically activate next term when previous closes?
 *       'require_approval_for_closure' => true,         // Principal must approve term/session closure?
 *       'require_reason_for_reopen' => true,            // Mandatory reason when reopening closed term
 *       'allow_date_extension_after_active' => true,    // Allow extending end_date after activation?
 *       'max_reopen_count_per_term' => 3,               // Prevent abuse of reopen feature
 *       'notify_on_activation' => true,                 // Send notifications when session/term activated?
 *       'notify_on_closure' => true,                    // Send notifications when session/term closed?
 *   ]
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.academic.session
 * - Navigation: Settings → Academic → Session Rules / Calendar Settings
 * - Frontend: resources/js/Pages/Settings/Academic/SessionRules.vue
 *
 * Permission: manage-settings (same as other settings pages)
 */
class AcademicSessionSettingsController extends Controller
{
    /**
     * Display the academic session settings form.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        // Get current settings (platform defaults merged with school overrides)
        $settings = getMergedSettings('academic.session_rules', $school);

        // Provide defaults if no settings exist yet
        $defaults = [
            'default_term_structure' => 'three_terms',
            'auto_activate_next_term' => true,
            'require_approval_for_closure' => true,
            'require_reason_for_reopen' => true,
            'allow_date_extension_after_active' => true,
            'max_reopen_count_per_term' => 3,
            'notify_on_activation' => true,
            'notify_on_closure' => true,
        ];

        return Inertia::render('Settings/Academic/SessionRules', [
            'settings' => array_merge($defaults, $settings ?? []),
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Academic'],
                ['label' => 'Session Rules'],
            ],
        ]);
    }

    /**
     * Store or update the academic session settings.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'default_term_structure' => 'required|in:three_terms,two_semesters,custom',
            'auto_activate_next_term' => 'required|boolean',
            'require_approval_for_closure' => 'required|boolean',
            'require_reason_for_reopen' => 'required|boolean',
            'allow_date_extension_after_active' => 'required|boolean',
            'max_reopen_count_per_term' => 'required|integer|min:1|max:10',
            'notify_on_activation' => 'required|boolean',
            'notify_on_closure' => 'required|boolean',
        ]);

        try {
            // Save settings (merge with existing, overwrite provided keys)
            SaveOrUpdateSchoolSettings('academic.session_rules', $validated, $school);

            return redirect()
                ->route('settings.academic.session')
                ->with('success', 'Academic session rules updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to save academic session rules', [
                'school_id' => $school?->id,
                'user_id'   => auth()->id(),
                'error'     => $e->getMessage(),
                'data'      => $validated,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save academic session rules.')
                ->withInput();
        }
    }
}
