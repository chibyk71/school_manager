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
            'school_name' => 'Default School Name',
            'short_name' => 'DSN',
            'motto' => 'Learning for the Future',
            'about' => 'This is a default school description.',
            'pledge' => 'Default pledge text.',
            'anthem' => 'Default anthem text.',
            'sidebar_default' => 'full',
            'table_pagination' => 10,
            'outside_click' => true,
            'start_day_of_week' => 0,
            'session_from' => Carbon::now(),
            'session_to' => Carbon::now()->addYear(),
        ];

        // Save the default settings to the database
        Settings::set('application', $defaultSettings);
    }
}