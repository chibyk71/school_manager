<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class ContactTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default contact settings
        $defaultSettings = [
            'phone' => '123-456-7890',
            'email' => 'contact@example.com',
            'facebook' => 'https://facebook.com/example',
            'twitter' => 'https://twitter.com/example',
            'instagram' => 'https://instagram.com/example',
            'linkedin' => 'https://linkedin.com/example',
            'youtube' => 'https://youtube.com/example',
            'map_embed_code' => '<iframe src="https://maps.google.com/..."></iframe>'
        ];

        // Save the default settings to the database
        Settings::set('contact', $defaultSettings);
    }
}