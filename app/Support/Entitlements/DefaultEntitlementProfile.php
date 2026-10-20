<?php

namespace App\Support\Entitlements;

/**
 * Code-defined default entitlement profile for Phase 1.
 *
 * Sole production default: file_manager.storage_bytes = 1 TiB
 * (1,099,511,627,776 bytes). No per-tenant configuration table and no
 * subscription system are required.
 *
 * Tests may construct an alternate profile or bind a different
 * EntitlementValueProvider without mutating production defaults.
 */
final class DefaultEntitlementProfile implements EntitlementValueProvider
{
    /** 1 TiB in bytes (1024^4). */
    public const FILE_MANAGER_STORAGE_BYTES = 1_099_511_627_776;

    public const FILE_MANAGER_STORAGE_KEY = 'file_manager.storage_bytes';

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
            default => null,
        };
    }
}
