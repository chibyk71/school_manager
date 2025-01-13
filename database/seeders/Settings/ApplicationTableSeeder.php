<?php

namespace Database\Seeders\Settings;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class ApplicationTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default application settings
        $defaultSettings = [
            'app_name' => 'Default School Name',
            'short_name' => 'DSN',
            'sidebar_default' => 'full',
            'table_pagination' => 50,
            'outside_click' => true,
            'allow_school_custom_logo' => true,
            'allow_school_default_payment' => true
        ];

        // Save the default settings to the database
        Settings::set('application', $defaultSettings);
    }
}