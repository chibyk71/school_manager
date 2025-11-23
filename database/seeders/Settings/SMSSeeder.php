<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use App\Models\School;
use Illuminate\Support\Facades\Log;

/**
 * SmsSettingsSeeder
 *
 * Seeds default SMS configuration for all schools.
 * Run once: php artisan db:seed --class=SmsSettingsSeeder
 *
 * Features:
 * - Adds all 14 providers if missing
 * - Sets logical default priorities (cheapest/fastest first)
 * - Leaves everything disabled by default
 * - Safe to run multiple times (idempotent)
 */
class SMSSeeder extends Seeder
{
    /**
     * Default SMS settings structure
     */
    private const DEFAULT_SMS_SETTINGS = [
        'enabled'                => false, // Master switch off by default
        'global_sender_id'       => null,
        'rate_limit_per_minute'  => 500,

        'providers' => [
            // Priority 10-50: Usually cheapest & most reliable in Nigeria
            'multitexter' => [
                'enabled'    => false,
                'priority'   => 10,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'bulk_sms_nigeria' => [
                'enabled'    => false,
                'priority'   => 15,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'smart_sms' => [
                'enabled'    => false,
                'priority'   => 20,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'beta_sms' => [
                'enabled'    => false,
                'priority'   => 25,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'gold_sms_247' => [
                'enabled'    => false,
                'priority'   => 30,
                'sender_id'  => null,
                'credentials' => [],
            ],

            // Priority 100+: International / premium
            'twilio' => [
                'enabled'    => false,
                'priority'   => 100,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'nexmo' => [
                'enabled'    => false,
                'priority'   => 110,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'africas_talking' => [
                'enabled'    => false,
                'priority'   => 120,
                'sender_id'  => null,
                'credentials' => [],
            ],

            // Others
            'kudi_sms' => [
                'enabled'    => false,
                'priority'   => 200,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'mebo_sms' => [
                'enabled'    => false,
                'priority'   => 210,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'nigerian_bulk_sms' => [
                'enabled'    => false,
                'priority'   => 220,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'x_wireless' => [
                'enabled'    => false,
                'priority'   => 230,
                'sender_id'  => null,
                'credentials' => [],
            ],
            'ring_captcha' => [
                'enabled'    => false,
                'priority'   => 300,
                'sender_id'  => null,
                'credentials' => [],
            ],
        ]
    ];

    /**
     * Run the seeder
     */
    public function run(): void
    {
        $totalSchools = School::count();

        if ($totalSchools === 0) {
            Log::info('SmsSettingsSeeder: No schools found. Skipping.');
            return;
        }

        Log::info("SmsSettingsSeeder: Processing {$totalSchools} schools...");

        School::chunk(50, function ($schools) {
            foreach ($schools as $school) {
                $current = getMergedSettings('sms', $school);

                // If already has some providers, skip (don't overwrite user config)
                if (!empty($current['providers'] ?? [])) {
                    continue;
                }

                // Save default structure
                try {
                    SaveOrUpdateSchoolSettings('sms', self::DEFAULT_SMS_SETTINGS, $school);
                    Log::info("SMS settings seeded for school ID {$school->id} - {$school->name}");
                } catch (\Exception $e) {
                    Log::error("Failed to seed SMS settings for school {$school->id}: " . $e->getMessage());
                }
            }
        });

        Log::info('SmsSettingsSeeder completed.');
    }
}