<?php

namespace App\Support\Entitlements;

use App\Support\Entitlements\Exceptions\InvalidEntitlementDefinitionException;
use App\Support\Entitlements\Exceptions\InvalidFeatureDefinitionException;

/**
 * Fluent builder used by module registrars to assemble a FeatureDefinition.
 *
 * Incomplete builders fail when converted — they are never silently discarded.
 */
final class FeatureRegistrar
{
    private string $name = '';

    private string $description = '';

    /** @var array<string, EntitlementDefinition> */
    private array $entitlements = [];

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(
        private readonly string $key,
        private readonly string $module,
    ) {}

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Register an entitlement on this feature.
     *
     * @param  array<string, mixed>  $constraints
     */
    public function entitlement(
        string $key,
        EntitlementType $type,
        mixed $default,
        string $label,
        string $description,
        array $constraints = [],
        ?string $scopeSemantics = null,
    ): self {
        if (isset($this->entitlements[$key])) {
            throw new InvalidEntitlementDefinitionException(
                $key,
                'Duplicate entitlement key within the same feature registration.'
            );
        }

        $this->entitlements[$key] = new EntitlementDefinition(
            key: $key,
            type: $type,
            default: $default,
            label: $label,
            description: $description,
            featureKey: $this->key,
            constraints: $constraints,
            scopeSemantics: $scopeSemantics,
        );

        return $this;
    }

    public function toFeatureDefinition(): FeatureDefinition
    {
        if ($this->name === '') {
            throw new InvalidFeatureDefinitionException(
                $this->key,
                'Display name is required before registration completes.'
            );
        }

        if ($this->description === '') {
            throw new InvalidFeatureDefinitionException(
                $this->key,
                'Description is required before registration completes.'
            );
        }

        return new FeatureDefinition(
            key: $this->key,
            module: $this->module,
            name: $this->name,
            description: $this->description,
            entitlements: $this->entitlements,
            metadata: $this->metadata,
        );
    }
}
