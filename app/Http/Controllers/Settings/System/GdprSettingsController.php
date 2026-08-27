<?php

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * GdprSettingsController v1.0 – Production-Ready GDPR & Cookie Consent Configuration
 *
 * Purpose:
 * Manages the cookie consent banner displayed to end-users (parents, students, visitors)
 * in compliance with GDPR/CCPA requirements. Allows customization of banner text, position,
 * buttons, and privacy policy link.
 *
 * Why stored in settings table:
 * - Cookie banner content is branding/compliance related and varies per school
 * - Frequently updated (legal text changes)
 * - Per-school overrides needed while inheriting global defaults
 * - Clean separation from core operational data
 *
 * Settings Key: 'system.gdpr'
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global + school overrides
 * - No abort() → system admin can set platform-wide defaults
 * - Full validation with sensible limits and URL checks
 * - Rich text support for content (HTML allowed, sanitized on frontend)
 * - Position options matching modern consent banners
 * - Optional decline button + custom texts
 * - Production-ready: input sanitization, error handling, structured logs
 * - Responsive form with live preview (frontend)
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.system.gdpr
 * - Navigation: System & Communication → GDPR & Cookies
 * - Frontend: resources/js/Pages/Settings/System/Gdpr.vue
 *
 * Default Values (for seeding on tenant bootstrap):
 *   'system.gdpr' => [
 *       'content_text' => 'We use cookies to improve your experience...',
 *       'position' => 'bottom',
 *       'show_accept_button' => true,
 *       'accept_button_text' => 'Accept All',
 *       'show_decline_button' => true,
 *       'decline_button_text' => 'Decline',
 *       'show_link' => true,
 *       'link_text' => 'Privacy Policy',
 *       'link_url' => 'https://yourschool.com/privacy',
 *   ]
 */

class GdprSettingsController extends Controller
{
    /**
     * Display the GDPR & Cookie consent settings page.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('system.gdpr', $school);

        return Inertia::render('Settings/System/GDPRCookies', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'System & Communication'],
                ['label' => 'GDPR & Cookies'],
            ],
            'positions' => ['top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right'],
        ]);
    }

    /**
     * Store/update GDPR & Cookie consent settings.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'content_text'          => 'required|string|max:5000', // Allow rich text
            'position'              => 'required|string|in:top,bottom,left,right,top-left,top-right,bottom-left,bottom-right',
            'show_accept_button'    => 'required|boolean',
            'accept_button_text'    => 'required_if:show_accept_button,true|string|max:100',
            'show_decline_button'   => 'required|boolean',
            'decline_button_text'   => 'required_if:show_decline_button,true|string|max:100',
            'show_link'             => 'required|boolean',
            'link_text'             => 'required_if:show_link,true|string|max:100',
            'link_url'              => 'required_if:show_link,true|url|max:500',
        ]);

        try {
            SaveOrUpdateSchoolSettings('system.gdpr', $validated, $school);

            return redirect()
                ->route('settings.system.gdpr')
                ->with('success', 'GDPR & Cookie settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('GDPR settings save failed', [
                'school_id' => $school?->id,
                'user_id'   => auth()->id(),
                'error'     => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save GDPR settings.')
                ->withInput();
        }
    }
}
