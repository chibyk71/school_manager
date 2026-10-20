<?php

namespace App\Support\Entitlements;

/**
 * Thin helper for feature-access (enabled/disabled) checks.
 *
 * Does not replace authorization or operation-capability checks.
 */
final class FeatureAccess
{
    public function __construct(
        private readonly EntitlementResolver $resolver,
    ) {}

    public function enabled(string $entitlementKey): bool
    {
        return $this->resolver->isEnabled($entitlementKey);
    }
}
