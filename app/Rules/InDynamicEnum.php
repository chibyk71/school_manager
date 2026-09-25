<?php

/**
 * Laravel validation adapter for Dynamic Enum selection (Phase 5).
 *
 * Thin adapter around DynamicEnumValidator. Does not resolve options, canonicalize,
 * or enforce requiredness itself — those belong to the domain validator.
 *
 * Consumers must pass an explicit definition key (e.g. profile.gender, address.type).
 * School context is taken from an explicit School argument when provided, otherwise
 * from GetSchoolModel(). When no school is available, tenant/default configuration
 * is used (validateTenant).
 *
 * Null values are always accepted by this rule; consumer field requiredness is
 * expressed separately via Laravel's required / nullable rules.
 */

namespace App\Rules;

use App\Models\School;
use App\Services\DynamicEnum\DynamicEnumValidationStatus;
use App\Services\DynamicEnum\DynamicEnumValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InDynamicEnum implements ValidationRule
{
    /**
     * @param  string  $key  Explicit Dynamic Enum definition key (e.g. profile.gender)
     * @param  School|null  $school  Explicit school for resolution.
     *                               Prefer passing GetSchoolModel() from school-scoped FormRequests
     *                               so validation cannot silently fall back to tenant baseline.
     *                               When null: uses GetSchoolModel(); if that is also null, validates
     *                               against tenant/default configuration (intentional tenant-only path).
     */
    public function __construct(
        protected string $key,
        protected ?School $school = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && ! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        /** @var string|null $stringValue */
        $stringValue = $value;

        $school = $this->school ?? (function_exists('GetSchoolModel') ? GetSchoolModel() : null);

        /** @var DynamicEnumValidator $validator */
        $validator = app(DynamicEnumValidator::class);

        $result = $school === null
            ? $validator->validateTenant($this->key, $stringValue)
            : $validator->validate($school, $this->key, $stringValue);

        if ($result->isValid()) {
            return;
        }

        match ($result->status) {
            DynamicEnumValidationStatus::DefinitionNotConfigured => $fail(
                'The :attribute configuration is unavailable.'
            ),
            DynamicEnumValidationStatus::InvalidOption => $fail(
                'The selected :attribute is invalid.'
            ),
            DynamicEnumValidationStatus::InactiveOption => $fail(
                'The selected :attribute is no longer available.'
            ),
            default => $fail('The selected :attribute is invalid.'),
        };
    }
}
