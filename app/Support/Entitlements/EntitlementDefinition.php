<?php

namespace App\Support\Entitlements;

/**
 * Immutable entitlement definition registered with a feature.
 *
 * The key is globally unique across the catalogue. Type, default, and
 * constraints are validated at registration time and again when values
 * are resolved.
 */
final class EntitlementDefinition
{
    /**
     * @param  array<string, mixed>  $constraints  Optional type-specific rules
     *                                             (min, max, allowed values).
     */
    public function __construct(
        public readonly string $key,
        public readonly EntitlementType $type,
        public readonly mixed $default,
        public readonly string $label,
        public readonly string $description,
        public readonly ?string $featureKey = null,
        public readonly array $constraints = [],
        public readonly ?string $scopeSemantics = null,
    ) {}
}
