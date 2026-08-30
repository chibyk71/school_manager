<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;

/**
 * Seeds academic.application defaults used by StudentApplicationService (Phase 2).
 *
 * Safe to re-run: uses SaveOrUpdateSchoolSettings (global defaults, school_id null).
 * Does not replace the main SettingsDefaultsSeeder; only ensures application keys exist.
 */
class ApplicationSettingsDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'required' => false,
            'fee_required' => false,
            'fee_amount' => null,
            'fee_type' => 'application_fee',
        ];

        // Merge with any existing global value so we don't wipe school overrides of other keys.
        $existing = [];
        if (function_exists('getMergedSettings')) {
            try {
                $existing = getMergedSettings('academic.application', null) ?? [];
            } catch (\Throwable $e) {
                $existing = [];
            }
        }

        $merged = array_merge($defaults, is_array($existing) ? $existing : []);

        // Prefer defaults for missing keys only
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $merged) || $merged[$key] === null || $merged[$key] === '') {
                $merged[$key] = $value;
            }
        }

        SaveOrUpdateSchoolSettings('academic.application', $merged, null);
    }
}
