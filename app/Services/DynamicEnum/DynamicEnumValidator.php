<?php

/**
 * Dynamic Enum Phase 3R — selection validation against effective resolution.
 *
 * Algorithm for a non-null selection value:
 *   1. Canonicalize input (trim + lowercase)
 *   2. Resolve effective enum (tenant + school sparse overlay)
 *   3. Find option by canonical value
 *   4. invalid_option if missing
 *   5. inactive_option if present but effective is_active = false
 *   6. valid otherwise
 *
 * null is not treated as invalid by Dynamic Enum — business-field nullability
 * remains the consumer's concern.
 *
 * Read-only: never mutates configuration. Merge logic lives only in the resolver.
 */

namespace App\Services\DynamicEnum;

use App\Models\School;

class DynamicEnumValidator
{
    public function __construct(
        private readonly DynamicEnumResolver $resolver,
    ) {}

    /**
     * Validate a candidate selection value for school + key.
     *
     * null is accepted (status valid) without applying option is_required.
     */
    public function validate(School $school, string $key, ?string $value): DynamicEnumValidationResult
    {
        if ($value === null) {
            try {
                $resolved = $this->resolver->resolveForSchool($school, $key);
            } catch (DynamicEnumNotConfiguredException) {
                return DynamicEnumValidationResult::definitionNotConfigured();
            }

            return DynamicEnumValidationResult::valid($resolved, null);
        }

        try {
            $resolved = $this->resolver->resolveForSchool($school, $key);
        } catch (DynamicEnumNotConfiguredException) {
            return DynamicEnumValidationResult::definitionNotConfigured();
        }

        $canonical = DynamicEnumValue::canonicalize($value);

        if ($canonical === '') {
            return DynamicEnumValidationResult::invalidOption($resolved);
        }

        $option = $resolved->findByValue($canonical);

        if ($option === null) {
            return DynamicEnumValidationResult::invalidOption($resolved);
        }

        if (! $option->isActive) {
            return DynamicEnumValidationResult::inactiveOption($resolved, $option);
        }

        return DynamicEnumValidationResult::valid($resolved, $option);
    }

    /**
     * Validate against tenant/default configuration (no school overlay).
     */
    public function validateTenant(string $key, ?string $value): DynamicEnumValidationResult
    {
        if ($value === null) {
            try {
                $resolved = $this->resolver->resolve($key);
            } catch (DynamicEnumNotConfiguredException) {
                return DynamicEnumValidationResult::definitionNotConfigured();
            }

            return DynamicEnumValidationResult::valid($resolved, null);
        }

        try {
            $resolved = $this->resolver->resolve($key);
        } catch (DynamicEnumNotConfiguredException) {
            return DynamicEnumValidationResult::definitionNotConfigured();
        }

        $canonical = DynamicEnumValue::canonicalize($value);

        if ($canonical === '') {
            return DynamicEnumValidationResult::invalidOption($resolved);
        }

        $option = $resolved->findByValue($canonical);

        if ($option === null) {
            return DynamicEnumValidationResult::invalidOption($resolved);
        }

        if (! $option->isActive) {
            return DynamicEnumValidationResult::inactiveOption($resolved, $option);
        }

        return DynamicEnumValidationResult::valid($resolved, $option);
    }

    /**
     * Whether a non-null value is selectable for new records under the effective definition.
     */
    public function isSelectable(School $school, string $key, string $value): bool
    {
        return $this->validate($school, $key, $value)->isValid();
    }

    /**
     * Find an effective option by value (recognizes inactive options).
     */
    public function findOption(School $school, string $key, string $value): ?ResolvedDynamicEnumOption
    {
        try {
            $resolved = $this->resolver->resolveForSchool($school, $key);
        } catch (DynamicEnumNotConfiguredException) {
            return null;
        }

        return $resolved->findByValue($value);
    }
}
