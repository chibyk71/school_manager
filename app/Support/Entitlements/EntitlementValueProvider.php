<?php

namespace App\Support\Entitlements;

/**
 * Boundary for effective entitlement values.
 *
 * Phase 1 provides code-defined defaults. A future SaaS / subscription
 * provider implements this interface and is bound in the container without
 * requiring consuming modules to change their resolution calls.
 */
interface EntitlementValueProvider
{
    /**
     * Return the effective raw value for an entitlement key, or null when
     * this provider does not supply a value (fall through to the next
     * provider / default profile).
     */
    public function get(string $entitlementKey): mixed;
}
