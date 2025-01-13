<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class EmailTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default email settings
        $defaultSettings = [
            'from_email' => 'default@example.com',
            'from_name' => 'Default Name',
            'mail_host' => 'smtp.example.com'
        ];

        // Save the default settings to the database
        Settings::set('email', $defaultSettings);
    }
}