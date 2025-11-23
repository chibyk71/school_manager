<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SMSSettingsRequest; // We'll use the validated request
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

/**
 * SMSController
 *
 * Handles viewing and updating SMS configuration for the active school.
 * Supports multiple providers, priority ordering, and fallback.
 *
 * Route names:
 *   - index:  settings.sms.index   → GET  /settings/sms
 *   - store:  settings.sms.store   → POST /settings/sms
 *
 * @package App\Http\Controllers\Settings\School
 */
class SMSController extends Controller
{
    /**
     * Display the SMS Settings page
     *
     * Loads merged SMS settings (tenant defaults + school overrides)
     * Seeds missing providers with defaults if first time
     *
     * @return Response
     */
    public function index(): Response
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            // Get merged settings + ensure all providers exist (for UI consistency)
            $settings = getMergedSettings('sms', $school);
            $settings = $this->ensureAllProvidersExist($settings);

            return Inertia::render('Settings/School/SMS', [
                'settings' => $settings,
                'canManage' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to load SMS settings page: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('dashboard')->with('error', 'Failed to load SMS settings.');
        }
    }

    /**
     * Store / Update SMS Settings
     *
     * Uses SMSSettingsRequest for smart validation
     * Encrypts passwords/tokens automatically
     *
     * @param SMSSettingsRequest $request
     * @return RedirectResponse
     */
    public function store(SMSSettingsRequest $request): RedirectResponse
    {
        try {
            permitted('manage-settings');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validated();

            // Optional: Encrypt sensitive credential fields
            foreach ($validated['providers'] ?? [] as $name => $provider) {
                if (!empty($provider['enabled']) && !empty($provider['credentials'])) {
                    foreach ($provider['credentials'] as $key => $value) {
                        if (in_array($key, ['password', 'auth_token', 'api_secret', 'token', 'api_key'])) {
                            if (!empty($value) && !str_starts_with($value, 'encrypted:')) {
                                $validated['providers'][$name]['credentials'][$key] = 'encrypted:' . encrypt($value);
                            }
                        }
                    }
                }
            }

            // Save using your existing helper
            SaveOrUpdateSchoolSettings('sms', $validated, $school);

            return redirect()
                ->route('settings.sms.index')
                ->with('success', 'SMS settings saved successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to save SMS settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save SMS settings. Please try again.')
                ->withInput();
        }
    }

    /**
     * Ensure all supported providers exist in settings (for consistent UI)
     * If a provider is missing, add it as disabled with default priority
     *
     * @param array $settings
     * @return array
     */
    private function ensureAllProvidersExist(array $settings): array
    {
        $allProviders = [
            'africas_talking', 'beta_sms', 'bulk_sms_nigeria', 'gold_sms_247',
            'kudi_sms', 'mebo_sms', 'multitexter', 'nigerian_bulk_sms',
            'nexmo', 'ring_captcha', 'smart_sms', 'twilio', 'x_wireless',
        ];

        $existing = $settings['providers'] ?? [];
        $newProviders = [];

        $nextPriority = collect($existing)->max('priority') ?? 10;

        foreach ($allProviders as $provider) {
            if (!isset($existing[$provider])) {
                $newProviders[$provider] = [
                    'enabled' => false,
                    'priority' => ++$nextPriority * 10, // spaced out
                    'sender_id' => null,
                    'credentials' => [],
                ];
            }
        }

        $settings['providers'] = array_merge($newProviders, $existing);

        // Ensure global fields exist
        $settings['enabled'] ??= true;
        $settings['global_sender_id'] ??= null;
        $settings['rate_limit_per_minute'] ??= 500;

        return $settings;
    }
}