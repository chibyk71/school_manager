<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class AuthenticationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $default = [
            'login_throttle_max' => 5,
            'login_throttle_lock' => 2,
            'reset_password_token_life' => 15,
            'allow_password_reset' => true,
            'enable_email_verification' => true,
            'allow_user_registration' => true,
            'account_approvel' => true,
            'oAuth_registration' => false,
            'show_terms_on_registration' => true
        ];

        Settings::set('authentication', $default);
    }
}
