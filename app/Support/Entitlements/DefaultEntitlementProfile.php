<?php

namespace App\Support\Entitlements;

/**
 * Code-defined default entitlement profile.
 *
 * Production defaults:
 * - file_manager.storage_bytes = 1 TiB
 * - schools.max_count = 1,000,000
 *
 * Tests may construct an alternate profile or bind a different
 * EntitlementValueProvider without mutating production defaults.
 */
final class DefaultEntitlementProfile implements EntitlementValueProvider
{
    /** 1 TiB in bytes (1024^4). */
    public const FILE_MANAGER_STORAGE_BYTES = 1_099_511_627_776;

    public const FILE_MANAGER_STORAGE_KEY = 'file_manager.storage_bytes';

    public const SCHOOLS_MAX_COUNT_KEY = 'schools.max_count';

    public const SCHOOLS_MAX_COUNT = 1_000_000;

    /**
     * @param  array<string, mixed>  $overrides  Optional test-only overrides.
     *                                           Production registration uses empty overrides.
     */
    public function __construct(
        private readonly array $overrides = [],
    ) {}

    public function get(string $entitlementKey): mixed
    {
        if (array_key_exists($entitlementKey, $this->overrides)) {
            return $this->overrides[$entitlementKey];
        }

        return match ($entitlementKey) {
            self::FILE_MANAGER_STORAGE_KEY => self::FILE_MANAGER_STORAGE_BYTES,
            self::SCHOOLS_MAX_COUNT_KEY => self::SCHOOLS_MAX_COUNT,
            default => null,
        };
    }
}
