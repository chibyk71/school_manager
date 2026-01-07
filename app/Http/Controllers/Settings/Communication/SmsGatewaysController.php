<?php

namespace App\Http\Controllers\Settings\Communication;

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
class SmsGatewaysController extends Controller
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
            // Provider definitions with real logos and fields
            $providers = [
                'nexmo' => [
                    'name' => 'Nexmo (Vonage)',
                    'logo' => 'nexmo',
                    'description' => 'Reliable global SMS delivery with excellent analytics.',
                    'fields' => ['api_key', 'api_secret', 'sender_id'],
                ],
                'twilio' => [
                    'name' => 'Twilio',
                    'logo' => 'twilio',
                    'description' => 'Industry-leading SMS with worldwide coverage and rich features.',
                    'fields' => ['account_sid', 'auth_token', 'sender_id'],
                ],
                'africas_talking' => [
                    'name' => "Africa's Talking",
                    'logo' => 'africas-talking',
                    'description' => 'Popular in Nigeria/Kenya with local numbers and great rates.',
                    'fields' => ['username', 'api_key', 'sender_id'],
                ],
                'multitexter' => [
                    'name' => 'Multitexter',
                    'logo' => 'multitexter',
                    'description' => 'Trusted Nigerian bulk SMS provider.',
                    'fields' => ['email', 'password', 'sender_id'],
                ],
                'bulk_sms_nigeria' => [
                    'name' => 'Bulk SMS Nigeria',
                    'logo' => 'bulk-sms-nigeria',
                    'description' => 'High-volume SMS gateway for Nigerian schools.',
                    'fields' => ['username', 'password', 'sender_id'],
                ],
                'beta_sms' => [
                    'name' => 'Beta SMS',
                    'logo' => 'beta-sms',
                    'description' => 'Cost-effective Nigerian SMS service.',
                    'fields' => ['username', 'password', 'sender_id'],
                ],
                'gold_sms_247' => [
                    'name' => 'Gold SMS 247',
                    'logo' => 'gold-sms',
                    'description' => 'Reliable Nigerian SMS provider.',
                    'fields' => ['username', 'password', 'sender_id'],
                ],
                'smart_sms' => [
                    'name' => 'Smart SMS',
                    'logo' => 'smart-sms',
                    'description' => 'Fast and affordable SMS delivery.',
                    'fields' => ['token', 'sender_id'],
                ],
                'x_wireless' => [
                    'name' => 'X Wireless',
                    'logo' => 'x-wireless',
                    'description' => 'Premium SMS gateway for Nigeria.',
                    'fields' => ['api_key', 'client_id', 'sender_id'],
                ],
                'kudi_sms' => [
                    'name' => 'Kudi SMS',
                    'logo' => 'kudi-sms',
                    'description' => 'Popular bulk SMS service in Nigeria.',
                    'fields' => ['username', 'password', 'sender_id'],
                ],
                'mebo_sms' => [
                    'name' => 'Mebo SMS',
                    'logo' => 'mebo-sms',
                    'description' => 'Reliable SMS delivery for schools.',
                    'fields' => ['api_key', 'sender_id'],
                ],
                'nigerian_bulk_sms' => [
                    'name' => 'Nigerian Bulk SMS',
                    'logo' => 'nigerian-bulk-sms',
                    'description' => 'High-volume SMS for Nigerian institutions.',
                    'fields' => ['username', 'password', 'sender_id'],
                ],
                'ring_captcha' => [
                    'name' => 'Ring Captcha',
                    'logo' => 'ring-captcha',
                    'description' => 'SMS with OTP and verification features.',
                    'fields' => ['api_key', 'sender_id'],
                ],
            ];

            return Inertia::render('Settings/System/SmsGateways', [
                'settings' => $settings,
                'providers' => $providers,
                'provider_logos' => [
                    'nexmo' => 'nexmo',
                    'twilio' => 'twilio',
                    'africas-talking' => 'africas-talking',
                    'multitexter' => 'multitexter',
                    'bulk-sms-nigeria' => 'bulk-sms-nigeria',
                    'beta-sms' => 'beta-sms',
                    'gold-sms' => 'gold-sms',
                    'smart-sms' => 'smart-sms',
                    'x-wireless' => 'x-wireless',
                    'kudi-sms' => 'kudi-sms',
                    'mebo-sms' => 'mebo-sms',
                    'nigerian-bulk-sms' => 'nigerian-bulk-sms',
                    'ring-captcha' => 'ring-captcha',
                ],
                'crumbs' => [
                    ['label' => 'Settings'],
                    ['label' => 'System & Communication'],
                    ['label' => 'SMS Gateways'],
                ],
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
            'africas_talking',
            'beta_sms',
            'bulk_sms_nigeria',
            'gold_sms_247',
            'kudi_sms',
            'mebo_sms',
            'multitexter',
            'nigerian_bulk_sms',
            'nexmo',
            'ring_captcha',
            'smart_sms',
            'twilio',
            'x_wireless',
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