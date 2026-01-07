<?php

namespace App\Http\Controllers\Settings\Advanced;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * IpBanController v1.0 – Production-Ready IP Address Ban Management
 *
 * Purpose:
 * Allows administrators to manually ban specific IP addresses from accessing the application.
 * Useful for blocking malicious users, bots, or repeated failed login attempts.
 *
 * Why this page is necessary:
 * - Laravel's built-in rate limiting is automatic, but sometimes manual bans are needed
 * - Schools may want to block known problematic IPs (e.g., repeated spam, abuse)
 * - Common in admin panels for security control
 * - Industry pattern: many systems have "IP Blacklist" or "Ban IP" feature
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform-wide bans
 * - Full CRUD: add, delete banned IPs
 * - Validation: valid IPv4/IPv6, no duplicates
 * - Optional reason and expiry date
 * - Responsive PrimeVue DataTable with actions
 * - Production-ready: validation, security, error handling, logging
 *
 * Settings Key: 'advanced.ip_bans'
 *
 * Structure:
 *   'advanced.ip_bans' => [
 *       'list' => [
 *           ['ip' => '192.168.1.100', 'reason' => 'Repeated failed logins', 'expires_at' => '2026-12-31'],
 *           ['ip' => '10.0.0.5', 'reason' => 'Spam', 'expires_at' => null],
 *       ]
 *   ]
 *
 * Middleware Integration Suggestion:
 * Create a middleware that checks request()->ip() against this list and returns 403 if banned.
 *
 * Fits into the Settings Module:
 * - Route: GET/POST/DELETE settings.advanced.ip
 * - Navigation: Other Settings → Ban IP Address
 * - Frontend: resources/js/Pages/Settings/Advanced/IpBan.vue
 */

class IpBanController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('advanced.ip_bans', $school);

        $settings['list'] = $settings['list'] ?? [];

        return Inertia::render('Settings/Advanced/IpBan', [
            'banned_ips' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Other Settings'],
                ['label' => 'Ban IP Address'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'ip' => 'required|ip',
            'reason' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:today',
        ]);

        try {
            $current = getMergedSettings('advanced.ip_bans', $school);
            $list = $current['list'] ?? [];

            // Prevent duplicates
            if (collect($list)->contains('ip', $validated['ip'])) {
                return redirect()->back()->with('error', 'This IP is already banned.');
            }

            $list[] = [
                'ip' => $validated['ip'],
                'reason' => $validated['reason'],
                'expires_at' => $validated['expires_at'] ?? null,
                'banned_at' => now()->toDateTimeString(),
            ];

            SaveOrUpdateSchoolSettings('advanced.ip_bans', ['list' => $list], $school);

            return redirect()
                ->route('settings.advanced.ip')
                ->with('success', 'IP address banned successfully.');
        } catch (\Exception $e) {
            Log::error('IP ban add failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to ban IP address.');
        }
    }

    public function destroy(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'ips' => 'required|array',
            'ips.*' => 'ip',
        ]);

        try {
            $current = getMergedSettings('advanced.ip_bans', $school);
            $list = $current['list'] ?? [];

            $remaining = array_filter($list, fn($entry) => !in_array($entry['ip'], $validated['ips']));

            SaveOrUpdateSchoolSettings('advanced.ip_bans', ['list' => array_values($remaining)], $school);

            return redirect()
                ->route('settings.advanced.ip')
                ->with('success', 'IP ban(s) removed successfully.');
        } catch (\Exception $e) {
            Log::error('IP ban remove failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to remove IP ban(s).');
        }
    }
}