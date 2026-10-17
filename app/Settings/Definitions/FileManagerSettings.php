<?php

namespace App\Settings\Definitions;

use App\Support\Settings\SettingsRegistry;

/**
 * File Manager policy settings (Phase 1).
 *
 * Provider connection records and secrets live in the storage_providers table,
 * not in generic Settings values. Only code-owned policy knobs belong here.
 *
 * Uses school scope only — system SettingsScope is not introduced.
 */
final class FileManagerSettings
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->group(
            'file_manager',
            'File Manager',
            function ($group): void {
                $group->define('file_manager.default_provider_preference')
                    ->type('string')
                    ->default('local')
                    ->label('Preferred default storage driver hint')
                    ->description(
                        'Advisory preference when creating new school storage providers. '
                        .'Does not change Laravel filesystem defaults or Media Library disk.'
                    )
                    ->intendedScope('school');

                $group->define('file_manager.allow_school_provider_management')
                    ->type('boolean')
                    ->default(true)
                    ->label('Allow school-level storage provider management')
                    ->description(
                        'When false, school actors with storage-providers.manage cannot create, '
                        .'update, or connection-test school-owned providers. Enforced by the '
                        .'storage provider controller via SettingsResolver. System-owned providers '
                        .'remain separately governed outside school context.'
                    )
                    ->intendedScope('school');
            }
        );
    }
}
