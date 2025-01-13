<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class MaintenanceTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default maintenance settings
        $defaultSettings = [
            'maintenance_mode' => 'off',
            'maintenance_key' => 'default-key',
            'maintenance_mode_url' => 'http://example.com/maintenance'
        ];

        // Save the default settings to the database
        Settings::set('maintenance', $defaultSettings);
    }
}