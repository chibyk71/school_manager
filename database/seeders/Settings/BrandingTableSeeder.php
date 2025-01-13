<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class BrandingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default branding settings
        $defaultSettings = [
            'logo' => 'default-logo.png',
            'small_logo' => 'default-small-logo.png',
            'favicon' => 'default-favicon.ico',
            'primary_color' => '#000000',
            'secondary_color' => '#FFFFFF',
            'accent_color' => '#FF0000'
        ];

        // Save the default settings to the database
        Settings::set('branding', $defaultSettings);
    }
}