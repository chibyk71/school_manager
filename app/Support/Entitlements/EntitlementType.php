<?php

namespace App\Support\Entitlements;

/**
 * Supported entitlement value types for Phase 1.
 *
 * Deliberately narrow: only types justified by the registry and File Manager
 * storage quota. Future phases may extend this enum without changing
 * existing resolution contracts.
 */
enum EntitlementType: string
{
    /** Boolean feature access: enabled / disabled. */
    case FeatureAccess = 'feature_access';

    /** Non-negative integer numeric limit. */
    case NumericLimit = 'numeric_limit';

    /** Non-negative integer count capacity (e.g. seats, items). */
    case CountCapacity = 'count_capacity';

    /** Non-negative integer storage capacity in bytes. */
    case StorageBytes = 'storage_bytes';
}
