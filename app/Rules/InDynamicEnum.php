<?php

/**
 * Temporary Phase 1 stub for the legacy InDynamicEnum validation rule.
 *
 * Phase 1 replaced the JSON Dynamic Enum schema and removed scopes such as
 * visibleToSchool() / forModel() and columns name / applies_to / options.
 *
 * Effective option resolution and selected-value validation belong to Phase 3.
 * Until then this rule is intentionally inert so request validation paths that
 * still reference it do not call removed model methods or missing columns.
 *
 * Do not use this as a long-term validation strategy.
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InDynamicEnum implements ValidationRule
{
    public bool $implicit = true;

    protected string $property;

    protected string $modelClass;

    public function __construct(string $property, string $modelClass)
    {
        $this->property = $property;
        $this->modelClass = $modelClass;
    }

    /**
     * Phase 1: no runtime Dynamic Enum resolution. Accept any value (including empty)
     * so legacy form/request paths remain executable until Phase 3 validation lands.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Intentionally no-op until Phase 3 resolution & validation.
    }
}
