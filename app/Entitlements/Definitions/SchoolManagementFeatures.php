<?php

namespace App\Entitlements\Definitions;

use App\Support\Entitlements\EntitlementRegistry;
use App\Support\Entitlements\EntitlementType;

/**
 * School Management Phase 1 feature and entitlement registration.
 *
 * schools.max_count — maximum counted schools (Draft/Ready/Active/Suspended)
 * in the current tenant database. Default 1,000,000.
 */
final class SchoolManagementFeatures
{
    public const FEATURE_KEY = 'school_management.schools';

    public const MAX_COUNT_KEY = 'schools.max_count';

    public const MAX_COUNT_DEFAULT = 1_000_000;

    public static function register(EntitlementRegistry $registry): void
    {
        $registry->feature(
            self::FEATURE_KEY,
            'school_management',
            function ($feature): void {
                $feature
                    ->name('School Management')
                    ->description(
                        'Institution identity, lifecycle and capacity for schools '
                        .'in the current tenant database.'
                    )
                    ->metadata([
                        'phase' => 1,
                        'consumer' => 'school_management',
                    ])
                    ->entitlement(
                        key: self::MAX_COUNT_KEY,
                        type: EntitlementType::CountCapacity,
                        default: self::MAX_COUNT_DEFAULT,
                        label: 'Maximum number of schools',
                        description: 'Maximum counted schools (Draft, Ready, Active, Suspended) '
                            .'in the current tenant database. Archived and soft-deleted schools '
                            .'do not count. Default is 1,000,000.',
                        constraints: [
                            'min' => 0,
                        ],
                        scopeSemantics: 'tenant_database',
                    );
            }
        );
    }
}
