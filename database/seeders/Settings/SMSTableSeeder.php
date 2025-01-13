<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class SMSTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default SMS settings
        $defaultSettings = [
            'sms_provider' => 'Twilio',
            'sms_api_key' => 'your-default-api-key',
            // Add more default settings as needed
        ];

        // Save the default settings to the database
        Settings::set('sms', $defaultSettings);
    }
}