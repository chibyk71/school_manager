<?php

namespace App\Entitlements\Definitions;

use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\EntitlementRegistry;
use App\Support\Entitlements\EntitlementType;

/**
 * File Manager feature and entitlement registration for Phase 1.
 *
 * Sole initial consumer of the Feature & Entitlement Registry.
 */
final class FileManagerFeatures
{
    public const FEATURE_KEY = 'file_manager.storage';

    public const STORAGE_BYTES_KEY = DefaultEntitlementProfile::FILE_MANAGER_STORAGE_KEY;

    public static function register(EntitlementRegistry $registry): void
    {
        $registry->feature(
            self::FEATURE_KEY,
            'file_manager',
            function ($feature): void {
                $feature
                    ->name('File Manager Storage')
                    ->description(
                        'Accounted storage capacity for File Manager-managed objects '
                        .'(ordinary uploads and trusted module-managed uploads). '
                        .'Direct Spatie Media Library uploads are not covered.'
                    )
                    ->metadata([
                        'phase' => 1,
                        'consumer' => 'file_manager',
                    ])
                    ->entitlement(
                        key: self::STORAGE_BYTES_KEY,
                        type: EntitlementType::StorageBytes,
                        default: DefaultEntitlementProfile::FILE_MANAGER_STORAGE_BYTES,
                        label: 'File Manager storage capacity (bytes)',
                        description: 'Maximum accounted File Manager-managed storage for the '
                            .'current tenant database. Default is 1 TiB (1,099,511,627,776 bytes). '
                            .'This is a high finite limit, not an unlimited sentinel. '
                            .'Usage includes active, trashed, and purge-in-progress objects plus '
                            .'outstanding reservations. Confirmed permanent deletion releases capacity.',
                        constraints: [
                            'min' => 0,
                        ],
                        scopeSemantics: 'tenant_database',
                    );
            }
        );
    }
}
