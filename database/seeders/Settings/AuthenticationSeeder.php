<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class AuthenticationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $default = [
            // Login Throttling (5 attempts, 2-minute lockout)
            'login_throttle_max' => 5,
            'login_throttle_lock' => 2,
            
            // Password Reset (15-minute token lifetime)
            'reset_password_token_life' => 15,
            'allow_password_reset' => true,
            'password_reset_max_attempts' => 5,
            
            // Email Verification (enabled by default)
            'enable_email_verification' => true,
            'otp_length' => 6,
            'otp_validity' => 10,
            'allow_otp_fallback' => false,
            
            // Registration (disabled by default per requirements)
            'allow_user_registration' => false,
            'account_approval' => true,
            'oAuth_registration' => false,
            'show_terms_on_registration' => true,
            
            // Password Confirmation (5-minute TTL)
            'require_password_confirmation' => true,
            'password_confirmation_ttl' => 1800, // 30 minutes
            
            // Password Change (enabled by default)
            'allow_password_change' => true,
            
            // Password Rules (8 characters, letters + numbers)
            'password_min_length' => 8,
            'password_require_letters' => true,
            'password_require_mixed_case' => false,
            'password_require_numbers' => true,
            'password_require_symbols' => false,
            
            // Rate Limiting
            'registration_max_attempts' => 5,
            'registration_lock_minutes' => 2,
            'password_update_max_attempts' => 5,
            'password_update_lock_minutes' => 1,
            'otp_verification_max_attempts' => 5,
        ];

        // Set global (tenant-level) settings
        Settings::set('authentication', $default);

        // Log the seeding
        \Log::info('Authentication settings seeded with defaults', $default);
    }
}