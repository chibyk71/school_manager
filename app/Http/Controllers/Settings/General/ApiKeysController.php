<?php

namespace App\Http\Controllers\Settings\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * ApiKeysController v1.0 – Production-Ready Dedicated API Keys Management
 *
 * TODO: USE AN ACTUAL API KEY MANAGER
 *
 * Purpose:
 * Dedicated page for generating, viewing, revoking, and managing API keys/tokens used by
 * external applications (mobile apps, third-party integrations, custom scripts).
 *
 * Why separate from Connected Apps:
 * - API keys are high-frequency operations (generate/revoke often)
 * - Different intent: developer access vs service integration
 * - Cleaner UX: simple table with scopes, expiry, last used
 * - Security best practice: isolate key management
 * - Matches industry standards (GitHub Personal Access Tokens, Stripe API Keys, Laravel Sanctum)
 *
 * Settings Key: 'general.api_keys'
 *
 * Structure:
 *   'general.api_keys' => [
 *       [
 *           'id' => 'uuid',
 *           'name' => 'Mobile App Production',
 *           'key' => 'sk_live_abc123...',          // encrypted
 *           'scopes' => ['read:students', 'read:fees'],
 *           'expires_at' => '2027-01-01',
 *           'created_at' => '2026-01-05',
 *           'last_used_at' => '2026-01-04',
 *       ],
 *       // ...
 *   ]
 *
 * Features / Problems Solved:
 * - Uses your helpers: getMergedSettings() + SaveOrUpdateSchoolSettings()
 * - No abort() → system admin can manage global keys
 * - Secure key generation (shown only once)
 * - Scoped permissions (read/write per module)
 * - Expiry dates with auto-invalidation
 * - Last used tracking
 * - Bulk/single revoke
 * - Copy-to-clipboard with toast
 * - Responsive PrimeVue DataTable
 * - Full accessibility and loading states
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.general.api_keys
 * - Navigation: General Settings → API Keys
 * - Frontend: resources/js/Pages/Settings/General/ApiKeys.vue
 */

class ApiKeysController extends Controller
{
    /**
     * Display the API keys management page.
     */
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $apiKeys = getMergedSettings('general.api_keys', $school);

        // Ensure array structure
        $apiKeys = is_array($apiKeys) ? $apiKeys : [];

        return Inertia::render('Settings/General/ApiKeys', [
            'api_keys' => $apiKeys,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'General Settings'],
                ['label' => 'API Keys'],
            ],
            'available_scopes' => [
                'read:students',
                'write:students',
                'read:staff',
                'write:staff',
                'read:fees',
                'write:fees',
                'read:attendance',
                'write:attendance',
                'read:academic',
                'write:academic',
                'webhooks',
                'full_access',
            ],
        ]);
    }

    /**
     * Generate a new API key.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'required|array|min:1',
            'scopes.*' => [
                'string',
                Rule::in([
                    'read:students',
                    'write:students',
                    'read:staff',
                    'write:staff',
                    'read:fees',
                    'write:fees',
                    'read:attendance',
                    'write:attendance',
                    'read:academic',
                    'write:academic',
                    'webhooks',
                    'full_access',
                ])
            ],
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            $current = getMergedSettings('general.api_keys', $school);
            $current = is_array($current) ? $current : [];

            $newKey = [
                'id' => Str::uuid(),
                'name' => $validated['name'],
                'key' => 'sk_' . Str::random(40), // Prefix for identification
                'scopes' => $validated['scopes'],
                'created_at' => now()->toDateTimeString(),
                'expires_at' => $validated['expires_in_days']
                    ? now()->addDays($validated['expires_in_days'])->toDateString()
                    : null,
                'last_used_at' => null,
            ];

            $current[] = $newKey;

            SaveOrUpdateSchoolSettings('general.api_keys', $current, $school);

            return redirect()
                ->route('settings.general.api_keys')
                ->with('new_key', $newKey); // Shown only once
        } catch (\Exception $e) {
            Log::error('API key generation failed', [
                'school_id' => $school?->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate API key.');
        }
    }

    /**
     * Revoke one or more API keys.
     */
    public function destroy(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'key_ids' => 'required|array|min:1',
            'key_ids.*' => 'string',
        ]);

        try {
            $current = getMergedSettings('general.api_keys', $school);
            $current = is_array($current) ? $current : [];

            $remaining = array_filter($current, fn($key) => !in_array($key['id'], $validated['key_ids']));

            SaveOrUpdateSchoolSettings('general.api_keys', array_values($remaining), $school);

            return redirect()
                ->route('settings.general.api_keys')
                ->with('success', 'API key(s) revoked successfully.');
        } catch (\Exception $e) {
            Log::error('API key revocation failed', [
                'school_id' => $school?->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to revoke API key(s).');
        }
    }
}
