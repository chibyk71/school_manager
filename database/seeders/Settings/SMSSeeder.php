<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

/**
 * Seed **sms** defaults.
 *
 * These defaults are *global* – each tenant can override them later.
 */
class SMSSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Provider (case-insensitive – we store lower-case)
            'sms_provider'   => 'termii',                 // most schools in Nigeria use Termii

            // Placeholder credentials – **must** be replaced per tenant
            'sms_api_key'    => 'YOUR_TERMII_API_KEY',
            'sms_sender_id'  => 'SchoolName',

            // Feature toggle
            'sms_enabled'    => false,                    // keep disabled until configured

            // Optional rate-limit (500 SMS/min = very safe)
            'sms_rate_limit_per_minute' => 500,
        ];

        Settings::set('sms', $defaults);

        \Log::info('SMSSeeder completed', $defaults);
    }
}
