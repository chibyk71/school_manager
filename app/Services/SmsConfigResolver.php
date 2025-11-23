<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * SmsConfigResolver
 *
 * Dynamically resolves SMS provider configuration at runtime for multi-tenant usage.
 * This ensures that config('laravel-sms.providers.xxx') returns the correct school-specific credentials
 * even though we primarily use send_sms() helper directly in SmsService.
 *
 * Why this exists:
 * - Some parts of djunehor/laravel-sms may read from config()
 * - Keeps the config cache consistent across requests
 * - Provides safe fallback for queues/console commands
 *
 * Usage:
 *   In config/laravel-sms.php → 'providers' => SmsConfigResolver::resolve(),
 *
 * @package App\Services
 */
class SmsConfigResolver
{
    /**
     * Resolve and return the full provider config array for the current context
     *
     * @return array
     */
    public static function resolve(): array
    {
        // 1. Console / Queue mode → return safe global fallback (from .env or empty)
        if (App::runningInConsole() || !app()->bound('schoolManager')) {
            return static::globalFallbackConfig();
        }

        // 2. Get current school from your tenancy system
        $school = GetSchoolModel();

        if (!$school) {
            Log::warning('SmsConfigResolver: No active school found, using fallback config');
            return static::globalFallbackConfig();
        }

        // 3. Load SMS settings from database
        $settings = getMergedSettings('sms', $school);

        if (empty($settings['providers'] ?? [])) {
            return static::globalFallbackConfig();
        }

        return static::buildProviderConfigFromSettings($settings);
    }

    /**
     * Build the exact structure expected by djunehor/laravel-sms config
     * Only includes enabled providers with valid credentials
     *
     * @param array $settings
     * @return array
     */
    private static function buildProviderConfigFromSettings(array $settings): array
    {
        $config = [];

        foreach ($settings['providers'] ?? [] as $name => $prov) {
            // Skip disabled providers
            if (empty($prov['enabled'])) {
                continue;
            }

            // Extract only the credentials (not priority, sender_id, etc.)
            $credentials = $prov['credentials'] ?? [];

            // Normalize provider name (in case stored with spaces/caps)
            $providerKey = strtolower(trim($name));

            // Map to the exact keys the package expects
            $mapped = match ($providerKey) {
                'multitexter',
                'gold_sms_247',
                'beta_sms',
                'kudi_sms',
                'nigerian_bulk_sms' => [
                    'username' => $credentials['username'] ?? null,
                    'password' => $credentials['password'] ?? null,
                ],
                'bulk_sms_nigeria' => [
                    'token' => $credentials['token'] ?? null,
                    'dnd'   => $credentials['dnd'] ?? 2,
                ],
                'twilio' => [
                    'account_sid' => $credentials['account_sid'] ?? null,
                    'auth_token'  => $credentials['auth_token'] ?? null,
                ],
                'africas_talking' => [
                    'api_key'  => $credentials['api_key'] ?? null,
                    'username' => $credentials['username'] ?? null,
                ],
                'nexmo' => [
                    'api_key'    => $credentials['api_key'] ?? null,
                    'api_secret' => $credentials['api_secret'] ?? null,
                ],
                'smart_sms', 'smslive247' => [
                    'token' => $credentials['token'] ?? null,
                ],
                'mebo_sms' => [
                    'api_key' => $credentials['api_key'] ?? null,
                    'dnd'     => $credentials['dnd'] ?? 0,
                ],
                'x_wireless' => [
                    'api_key'   => $credentials['api_key'] ?? null,
                    'client_id' => $credentials['client_id'] ?? null,
                ],
                // Add more as needed
                default => $credentials,
            };

            // Only add if at least one credential exists
            if (array_filter($mapped)) {
                $config[$providerKey] = $mapped;
            }
        }

        return $config;
    }

    /**
     * Global fallback config – used when no school context or during console/queue
     * You can leave this empty or pull from .env for admin/testing
     *
     * @return array
     */
    private static function globalFallbackConfig(): array
    {
        return [
            // Example: keep one for testing (optional)
            // 'multitexter' => [
            //     'username' => env('MULTITEXTER_USERNAME'),
            //     'password' => env('MULTITEXTER_PASSWORD'),
            // ],
        ];
    }
}