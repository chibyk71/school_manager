<?php

namespace App\Http\Controllers\Settings\Advanced;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * MaintenanceSettingsController v1.0 – Production-Ready Maintenance Mode Configuration
 *
 * Purpose:
 * Central configuration for enabling/disabling maintenance mode, setting a bypass key,
 * and defining a custom maintenance page URL (for branding or detailed message).
 *
 * Why this page is essential:
 * - Allows admins to take the school portal offline for updates, migrations, or emergencies
 * - Bypass key enables authorized users (admins) to access during maintenance
 * - Custom URL supports branded maintenance page (e.g., with school logo, contact info)
 * - Industry standard: most SaaS platforms have maintenance mode settings
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform defaults
 * - Secure bypass key (stored plain – checked in middleware)
 * - Custom URL for branded maintenance page
 * - Clean, simple form with toggle and inputs
 * - Responsive PrimeVue layout
 * - Production-ready: validation, error handling, structured logs
 *
 * Settings Key: 'others.maintenance'
 *
 * Structure:
 *   'others.maintenance' => [
 *       'mode' => 'disabled', // 'enabled' or 'disabled'
 *       'bypass_key' => 'secret123',
 *       'custom_url' => 'https://yourschool.com/maintenance',
 *   ]
 *
 * Middleware Integration Suggestion:
 * Create middleware that checks if mode === 'enabled' and request doesn't have ?key=bypass_key → redirect to custom_url or default maintenance view.
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.others.maintenance
 * - Navigation: Other Settings → Maintenance Mode
 * - Frontend: resources/js/Pages/Settings/Others/Maintenance.vue
 */

class MaintenanceSettingsController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('maintenance', $school);

        return Inertia::render('Settings/Advanced/Maintenance', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Other Settings'],
                ['label' => 'Maintenance Mode'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'mode' => 'required|in:enabled,disabled',
            'bypass_key' => 'required|string|min:8|max:255',
            'custom_url' => 'nullable|url|max:500',
        ]);

        try {
            SaveOrUpdateSchoolSettings('maintenance', $validated, $school);

            return redirect()
                ->route('settings.advanced.maintenance')
                ->with('success', 'Maintenance mode settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Maintenance settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save maintenance settings.')
                ->withInput();
        }
    }
}
