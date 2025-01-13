<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class SMTPTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default SMTP settings
        $defaultSettings = [
            'smtpHost' => 'smtp.example.com',
            'smtpPort' => 587,
            'smtpUser' => 'user@example.com',
            'smtpPassword' => 'password',
            'smtpFromEmail' => 'no-reply@example.com'
        ];

        // Save the default settings to the database
        Settings::set('smtp', $defaultSettings);
    }
}