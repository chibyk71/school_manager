<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;

/**
 * LEGACY REFERENCE ONLY — not part of the OI Settings runtime.
 *
 * Historical academic.application defaults used by StudentApplicationService.
 * Phase 1 removed ruangdeveloper/laravel-settings and the getMergedSettings /
 * SaveOrUpdateSchoolSettings helpers. Re-register these keys under OI when
 * the application module migrates its settings (not Phase 1).
 *
 * @deprecated Historical reference for domain migration. Safe no-op.
 */
class ApplicationSettingsDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        // No-op: legacy Settings package removed in Phase 1.
        // Historical defaults for documentation:
        $defaults = [
            'required' => false,
            'fee_required' => false,
            'fee_amount' => null,
            'fee_type' => 'application_fee',
        ];
        unset($defaults);
    }
}
