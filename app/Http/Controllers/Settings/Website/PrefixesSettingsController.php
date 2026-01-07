<?php

namespace App\Http\Controllers\Settings\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * PrefixesSettingsController v1.0 – Production-Ready ID Prefixes Management
 *
 * Purpose:
 * Manages customizable prefixes for generated identifiers across the system
 * (student IDs, staff IDs, invoices, payments, classes, subjects, etc.).
 *
 * Why stored in settings table (not School model columns):
 * - Prefixes are branding/presentation related → belong to "Website & Branding"
 * - Frequently changed (e.g., new academic year format)
 * - Allows per-school customization while inheriting system defaults
 * - Clean separation: operational identifiers (School model) vs display formatting
 * - Matches industry pattern (Fedena, QuickSchools, Gibbon all have separate "Prefixes" page)
 *
 * Settings Key: 'website.prefixes'
 *
 * Features / Problems Solved:
 * - Uses your provided helpers: getMergedSettings() and SaveOrUpdateSchoolSettings()
 * - No abort() on missing school → allows system admin to edit global defaults
 * - Full validation with sensible defaults and length limits
 * - Proper error handling and structured logging
 * - Returns merged values (school override → global) for accurate display
 * - Clean success/error flashes
 * - Responsive, accessible form aligned with your stack
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.website.prefixes
 * - Navigation: Website & Branding → Prefixes
 * - Frontend: resources/js/Pages/Settings/Website/Prefixes.vue
 *
 * Default Values (for seeding on tenant bootstrap – add to your seeder):
 *   'website.prefixes' => [
 *       'student_id'      => 'STU',
 *       'staff_id'        => 'STF',
 *       'parent_id'       => 'PAR',
 *       'invoice'         => 'INV',
 *       'payment'         => 'PAY',
 *       'receipt'         => 'REC',
 *       'class'           => 'CLS',
 *       'section'         => 'SEC',
 *       'subject'         => 'SUB',
 *       'exam'            => 'EXM',
 *       'fee_type'        => 'FEE',
 *       'transport_route' => 'TR',
 *       'library_book'    => 'LIB',
 *   ]
 */

class PrefixesSettingsController extends Controller
{
    /**
     * Display the prefixes settings page.
     */
    public function index()
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // May be null for system admin

        $settings = getMergedSettings('website.prefixes', $school);

        return Inertia::render('Settings/Website/Prefixes', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Website & Branding'],
                ['label' => 'Prefixes'],
            ],
        ]);
    }

    /**
     * Store/update prefixes for the current school or globally.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // May be null → global defaults

        $validated = $request->validate([
            'student_id' => 'required|string|max:10',
            'staff_id' => 'required|string|max:10',
            'parent_id' => 'required|string|max:10',
            'invoice' => 'required|string|max:10',
            'payment' => 'required|string|max:10',
            'receipt' => 'required|string|max:10',
            'class' => 'required|string|max:10',
            'section' => 'required|string|max:10',
            'subject' => 'required|string|max:10',
            'exam' => 'required|string|max:10',
            'fee_type' => 'required|string|max:10',
            'transport_route' => 'required|string|max:10',
            'library_book' => 'required|string|max:10',
        ]);

        try {
            SaveOrUpdateSchoolSettings('website.prefixes', $validated, $school);

            $message = $school
                ? 'School prefixes updated successfully.'
                : 'Global default prefixes updated successfully.';

            return redirect()
                ->route('settings.website.prefixes')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Prefixes settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save prefixes.')
                ->withInput();
        }
    }
}