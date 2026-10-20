<?php

namespace App\Support\Entitlements;

/**
 * Immutable feature definition in the central catalogue.
 *
 * @param  array<string, EntitlementDefinition>  $entitlements  Keyed by entitlement key.
 */
final class FeatureDefinition
{
    /**
     * @param  array<string, EntitlementDefinition>  $entitlements
     */
    public function __construct(
        public readonly string $key,
        public readonly string $module,
        public readonly string $name,
        public readonly string $description,
        public readonly array $entitlements = [],
        public readonly array $metadata = [],
    ) {}
}
