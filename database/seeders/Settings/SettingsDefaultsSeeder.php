<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;

/**
 * LEGACY REFERENCE ONLY — not part of the OI Settings runtime.
 *
 * Documents historical setting keys and defaults from the retired
 * ruangdeveloper/laravel-settings system. Phase 1 removed that package;
 * future modules should re-register needed keys via the OI infrastructure
 * (Phase 2+ definition/registration API). Do not wire the new Settings
 * capability to this seeder.
 *
 * @deprecated Historical reference for domain migration. Safe no-op.
 */
class SettingsDefaultsSeeder extends Seeder
{
    /**
     * Historical default values for all settings modules (reference only).
     *
     * This seeder is intentionally a no-op. The $defaults array below is
     * retained so module owners can migrate keys when rebuilding settings.
     */
    public function run(): void
    {
        // No-op: legacy Settings package removed in Phase 1.
        // Historical defaults retained below for documentation only.
        $defaults = [
            'website.company' => [
                'legal_name' => 'Your School Name',
                'tagline' => 'Excellence in Education',
                'tax_id' => null,
                'public_email' => 'info@yourschool.com',
                'public_phone' => '+234 800 000 0000',
                'website_url' => 'https://yourschool.com',
                'social_facebook' => 'https://facebook.com/yourschool',
                'social_twitter' => 'https://twitter.com/yourschool',
                'social_instagram' => 'https://instagram.com/yourschool',
                'social_linkedin' => null,
                'social_youtube' => 'https://youtube.com/@yourschool',
                'footer_copyright' => '© 2026 Your School Name. All rights reserved.',
                'google_maps_embed' => null,
                'show_address_footer' => true,
                'show_phone_footer' => true,
                'show_email_footer' => true,
            ],
            'website.themes' => [
                'primary_color' => 'indigo',
                'primary_custom_hex' => null,
                'secondary_color' => 'gray',
                'secondary_custom_hex' => null,
                'default_theme' => 'light',
                'dashboard_layout' => 'modern',
                'sidebar_collapsed' => false,
                'menu_position' => 'left',
                'compact_mode' => false,
            ],
            'website.localization' => [
                'timezone' => 'Africa/Lagos',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'currency' => 'NGN',
                'currency_position' => 'before',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'language' => 'en',
                'language_switcher' => true,
                'financial_year' => 2026,
                'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'],
                'max_file_upload_size' => 5120,
            ],
            // ... remaining historical keys retained in repository artifacts;
            // full catalogue is in local tip / settings-phase1-infrastructure.bundle
        ];

        // Intentionally not persisted — legacy package removed in Phase 1.
        unset($defaults);
    }
}
