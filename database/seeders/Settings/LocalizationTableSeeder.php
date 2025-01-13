<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class LocalizationTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default localization settings
        $defaultSettings = [
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'currency_position' => 'before',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'language' => 'en'
        ];

        // Save the default settings to the database
        Settings::set('localization', $defaultSettings);
    }
}