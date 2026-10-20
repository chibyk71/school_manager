<?php

namespace App\Support\Entitlements;

use App\Support\Entitlements\Exceptions\InvalidEntitlementValueException;
use App\Support\Entitlements\Exceptions\UnknownEntitlementException;
use App\Support\Entitlements\Exceptions\UnknownFeatureException;

/**
 * Stable contract for resolving effective entitlement values.
 *
 * Consuming modules depend only on this resolver. The active
 * EntitlementValueProvider supplies values; the registry validates
 * type and constraints. Unknown keys fail closed.
 */
final class EntitlementResolver
{
    /**
     * @param  list<EntitlementValueProvider>  $providers  Highest priority first.
     *                                                     Default profile is always last.
     */
    public function __construct(
        private readonly EntitlementRegistry $registry,
        private readonly array $providers = [],
        private readonly ?DefaultEntitlementProfile $defaultProfile = null,
    ) {}

    /**
     * Resolve and validate the effective value for an entitlement key.
     *
     * @throws UnknownEntitlementException
     * @throws InvalidEntitlementValueException
     */
    public function value(string $entitlementKey): mixed
    {
        $definition = $this->registry->getEntitlement($entitlementKey);

        $raw = $this->resolveRaw($entitlementKey);

        if ($raw === null) {
            // Fall back to the definition default (always present after registration validation).
            $raw = $definition->default;
        }

        try {
            return $this->registry->validateValue($entitlementKey, $raw);
        } catch (\Throwable $e) {
            throw new InvalidEntitlementValueException(
                $entitlementKey,
                $e->getMessage(),
                $raw
            );
        }
    }

    /**
     * Resolve a feature-access entitlement as a strict boolean.
     *
     * @throws UnknownEntitlementException
     * @throws InvalidEntitlementValueException
     */
    public function isEnabled(string $entitlementKey): bool
    {
        $value = $this->value($entitlementKey);

        if (! is_bool($value)) {
            throw new InvalidEntitlementValueException(
                $entitlementKey,
                'Expected feature_access boolean.',
                $value
            );
        }

        return $value;
    }

    /**
     * Resolve a numeric / capacity entitlement as a non-negative int.
     *
     * @throws UnknownEntitlementException
     * @throws InvalidEntitlementValueException
     */
    public function limit(string $entitlementKey): int
    {
        $value = $this->value($entitlementKey);

        if (! is_int($value) || $value < 0) {
            throw new InvalidEntitlementValueException(
                $entitlementKey,
                'Expected non-negative integer capacity/limit.',
                $value
            );
        }

        return $value;
    }

    /**
     * Ensure a feature is registered (fail closed for unknown product features).
     *
     * @throws UnknownFeatureException
     */
    public function assertFeatureRegistered(string $featureKey): void
    {
        $this->registry->getFeature($featureKey);
    }

    private function resolveRaw(string $entitlementKey): mixed
    {
        foreach ($this->providers as $provider) {
            $value = $provider->get($entitlementKey);
            if ($value !== null) {
                return $value;
            }
        }

        if ($this->defaultProfile !== null) {
            $value = $this->defaultProfile->get($entitlementKey);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
