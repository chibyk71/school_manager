<?php

namespace App\Support\Entitlements;

use App\Support\Entitlements\Exceptions\InvalidEntitlementDefinitionException;
use App\Support\Entitlements\Exceptions\InvalidFeatureDefinitionException;
use App\Support\Entitlements\Exceptions\UnknownEntitlementException;
use App\Support\Entitlements\Exceptions\UnknownFeatureException;

/**
 * Central, code-owned feature catalogue and entitlement definition registry.
 *
 * Registration is deterministic, validated, and sealed at bootstrap.
 * Duplicate feature keys, conflicting entitlement keys, and invalid
 * metadata are rejected with actionable errors. No silent overwrite.
 */
final class EntitlementRegistry
{
    /** @var array<string, FeatureDefinition> */
    private array $features = [];

    /** @var array<string, EntitlementDefinition> */
    private array $entitlements = [];

    private bool $sealed = false;

    /**
     * Register a complete feature definition with its entitlements.
     *
     * @throws InvalidFeatureDefinitionException
     * @throws InvalidEntitlementDefinitionException
     */
    public function register(FeatureDefinition $feature): void
    {
        if ($this->sealed) {
            throw new InvalidFeatureDefinitionException(
                $feature->key,
                'Cannot register after the registry has been sealed.'
            );
        }

        $this->assertValidFeature($feature);

        if (isset($this->features[$feature->key])) {
            throw new InvalidFeatureDefinitionException(
                $feature->key,
                'Duplicate feature identifier.'
            );
        }

        foreach ($feature->entitlements as $entitlement) {
            $this->assertValidEntitlement($entitlement, $feature->key);

            if (isset($this->entitlements[$entitlement->key])) {
                throw new InvalidEntitlementDefinitionException(
                    $entitlement->key,
                    'Conflicting entitlement key already registered '
                    ."(owner feature [{$this->entitlements[$entitlement->key]->featureKey}])."
                );
            }
        }

        $this->features[$feature->key] = $feature;

        foreach ($feature->entitlements as $key => $entitlement) {
            // Re-bind with feature key for discovery.
            $bound = new EntitlementDefinition(
                key: $entitlement->key,
                type: $entitlement->type,
                default: $entitlement->default,
                label: $entitlement->label,
                description: $entitlement->description,
                featureKey: $feature->key,
                constraints: $entitlement->constraints,
                scopeSemantics: $entitlement->scopeSemantics,
            );
            $this->entitlements[$key] = $bound;
        }
    }

    /**
     * Fluent registration helper used by module registrars.
     *
     * @param  callable(FeatureRegistrar): void  $callback
     */
    public function feature(string $key, string $module, callable $callback): void
    {
        $registrar = new FeatureRegistrar($key, $module);
        $callback($registrar);
        $this->register($registrar->toFeatureDefinition());
    }

    public function seal(): void
    {
        $this->sealed = true;
    }

    public function isSealed(): bool
    {
        return $this->sealed;
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public function allFeatures(): array
    {
        return $this->features;
    }

    /**
     * @return array<string, EntitlementDefinition>
     */
    public function allEntitlements(): array
    {
        return $this->entitlements;
    }

    public function hasFeature(string $key): bool
    {
        return isset($this->features[$key]);
    }

    public function hasEntitlement(string $key): bool
    {
        return isset($this->entitlements[$key]);
    }

    /**
     * @throws UnknownFeatureException
     */
    public function getFeature(string $key): FeatureDefinition
    {
        if (! isset($this->features[$key])) {
            throw new UnknownFeatureException($key);
        }

        return $this->features[$key];
    }

    /**
     * @throws UnknownEntitlementException
     */
    public function getEntitlement(string $key): EntitlementDefinition
    {
        if (! isset($this->entitlements[$key])) {
            throw new UnknownEntitlementException($key);
        }

        return $this->entitlements[$key];
    }

    /**
     * Validate a candidate value against a registered entitlement definition.
     *
     * @throws UnknownEntitlementException
     * @throws InvalidEntitlementDefinitionException  when value fails constraints
     */
    public function validateValue(string $entitlementKey, mixed $value): mixed
    {
        $definition = $this->getEntitlement($entitlementKey);

        return $this->normalizeAndValidate($definition, $value);
    }

    /**
     * @throws InvalidFeatureDefinitionException
     */
    private function assertValidFeature(FeatureDefinition $feature): void
    {
        if ($feature->key === '') {
            throw new InvalidFeatureDefinitionException(null, 'Feature key must not be empty.');
        }

        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', $feature->key)) {
            throw new InvalidFeatureDefinitionException(
                $feature->key,
                'Feature key must be dot-separated lowercase identifiers (e.g. file_manager.storage).'
            );
        }

        if ($feature->module === '') {
            throw new InvalidFeatureDefinitionException($feature->key, 'Owning module must not be empty.');
        }

        if ($feature->name === '') {
            throw new InvalidFeatureDefinitionException($feature->key, 'Display name must not be empty.');
        }

        if ($feature->description === '') {
            throw new InvalidFeatureDefinitionException($feature->key, 'Description must not be empty.');
        }
    }

    /**
     * @throws InvalidEntitlementDefinitionException
     */
    private function assertValidEntitlement(EntitlementDefinition $entitlement, string $featureKey): void
    {
        if ($entitlement->key === '') {
            throw new InvalidEntitlementDefinitionException(null, 'Entitlement key must not be empty.');
        }

        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', $entitlement->key)) {
            throw new InvalidEntitlementDefinitionException(
                $entitlement->key,
                'Entitlement key must be dot-separated lowercase identifiers.'
            );
        }

        if ($entitlement->label === '') {
            throw new InvalidEntitlementDefinitionException($entitlement->key, 'Label must not be empty.');
        }

        if ($entitlement->description === '') {
            throw new InvalidEntitlementDefinitionException($entitlement->key, 'Description must not be empty.');
        }

        // Default is required for all Phase 1 types.
        try {
            $this->normalizeAndValidate($entitlement, $entitlement->default);
        } catch (InvalidEntitlementDefinitionException $e) {
            throw new InvalidEntitlementDefinitionException(
                $entitlement->key,
                'Default value is invalid: '.$e->getMessage()
            );
        }
    }

    /**
     * @throws InvalidEntitlementDefinitionException
     */
    private function normalizeAndValidate(EntitlementDefinition $definition, mixed $value): mixed
    {
        return match ($definition->type) {
            EntitlementType::FeatureAccess => $this->validateFeatureAccess($definition, $value),
            EntitlementType::NumericLimit,
            EntitlementType::CountCapacity,
            EntitlementType::StorageBytes => $this->validateNonNegativeInteger($definition, $value),
        };
    }

    /**
     * @throws InvalidEntitlementDefinitionException
     */
    private function validateFeatureAccess(EntitlementDefinition $definition, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw new InvalidEntitlementDefinitionException(
            $definition->key,
            'Feature access value must be boolean-compatible.'
        );
    }

    /**
     * @throws InvalidEntitlementDefinitionException
     */
    private function validateNonNegativeInteger(EntitlementDefinition $definition, mixed $value): int
    {
        if (is_int($value)) {
            $normalized = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
            $normalized = (int) $value;
        } else {
            throw new InvalidEntitlementDefinitionException(
                $definition->key,
                'Value must be a non-negative integer (PHP int or pure digit string).'
            );
        }

        if ($normalized < 0) {
            throw new InvalidEntitlementDefinitionException(
                $definition->key,
                'Value must be non-negative.'
            );
        }

        if (isset($definition->constraints['min']) && $normalized < (int) $definition->constraints['min']) {
            throw new InvalidEntitlementDefinitionException(
                $definition->key,
                "Value must be >= {$definition->constraints['min']}."
            );
        }

        if (isset($definition->constraints['max']) && $normalized > (int) $definition->constraints['max']) {
            throw new InvalidEntitlementDefinitionException(
                $definition->key,
                "Value must be <= {$definition->constraints['max']}."
            );
        }

        return $normalized;
    }
}
