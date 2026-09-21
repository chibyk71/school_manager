<?php

/**
 * Dynamic Enum Phase 3 — selection validation against the effective definition.
 *
 * Algorithm for a new selection value:
 *   1. Resolve effective definition (school override → tenant default)
 *   2. definition_not_configured if none
 *   3. Find option by exact stable value
 *   4. invalid_option if missing
 *   5. inactive_option if present but is_active = false
 *   6. valid otherwise
 *
 * null is not treated as invalid by Dynamic Enum requiredness — business-field
 * nullability is the consumer's concern. is_required on options is configuration
 * protection only.
 *
 * Read-only: never mutates configuration.
 */

namespace App\Services\DynamicEnum;

use App\Models\DynamicEnumOption;
use App\Models\School;

class DynamicEnumValidator
{
    public function __construct(
        private readonly DynamicEnumResolver $resolver,
    ) {
    }

    /**
     * Validate a candidate selection value for school + key.
     *
     * null is accepted (status valid) without applying option is_required;
     * the consumer decides whether the business field is nullable.
     */
    public function validate(School $school, string $key, ?string $value): DynamicEnumValidationResult
    {
        if ($value === null) {
            $definition = $this->resolver->resolve($school, $key);

            return DynamicEnumValidationResult::valid($definition, null);
        }

        $definition = $this->resolver->resolveWithOptions($school, $key);

        if ($definition === null) {
            return DynamicEnumValidationResult::definitionNotConfigured();
        }

        /** @var DynamicEnumOption|null $option */
        $option = $definition->options->firstWhere('value', $value);

        if ($option === null) {
            return DynamicEnumValidationResult::invalidOption($definition);
        }

        if (! $option->is_active) {
            return DynamicEnumValidationResult::inactiveOption($definition, $option);
        }

        return DynamicEnumValidationResult::valid($definition, $option);
    }

    /**
     * Whether a non-null value is selectable for new records under the effective definition.
     */
    public function isSelectable(School $school, string $key, string $value): bool
    {
        return $this->validate($school, $key, $value)->isValid();
    }

    /**
     * Find an option by exact value in the effective definition (active or inactive).
     * Returns null when definition missing or value unknown.
     * Does not filter on is_active — inactive options remain recognizable.
     */
    public function findOption(School $school, string $key, string $value): ?DynamicEnumOption
    {
        $definition = $this->resolver->resolveWithOptions($school, $key);

        if ($definition === null) {
            return null;
        }

        return $definition->options->firstWhere('value', $value);
    }
}
