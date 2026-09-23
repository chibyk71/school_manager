<?php

namespace App\Services\DynamicEnum;

/**
 * Structured result of Dynamic Enum selection validation (Phase 3R).
 *
 * Callers should branch on status rather than exception messages.
 * option is populated when the value exists in the effective configuration
 * (including inactive options, so historical identity remains recognizable).
 * option may be null when validating a null business value (not a selection error).
 */
final class DynamicEnumValidationResult
{
    public function __construct(
        public readonly DynamicEnumValidationStatus $status,
        public readonly ?ResolvedDynamicEnum $definition = null,
        public readonly ?ResolvedDynamicEnumOption $option = null,
    ) {}

    public function isValid(): bool
    {
        return $this->status === DynamicEnumValidationStatus::Valid;
    }

    public function isDefinitionNotConfigured(): bool
    {
        return $this->status === DynamicEnumValidationStatus::DefinitionNotConfigured;
    }

    public function isInvalidOption(): bool
    {
        return $this->status === DynamicEnumValidationStatus::InvalidOption;
    }

    public function isInactiveOption(): bool
    {
        return $this->status === DynamicEnumValidationStatus::InactiveOption;
    }

    public static function valid(?ResolvedDynamicEnum $definition = null, ?ResolvedDynamicEnumOption $option = null): self
    {
        return new self(DynamicEnumValidationStatus::Valid, $definition, $option);
    }

    public static function definitionNotConfigured(): self
    {
        return new self(DynamicEnumValidationStatus::DefinitionNotConfigured);
    }

    public static function invalidOption(?ResolvedDynamicEnum $definition = null): self
    {
        return new self(DynamicEnumValidationStatus::InvalidOption, $definition);
    }

    public static function inactiveOption(ResolvedDynamicEnum $definition, ResolvedDynamicEnumOption $option): self
    {
        return new self(DynamicEnumValidationStatus::InactiveOption, $definition, $option);
    }
}
