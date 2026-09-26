<?php

/**
 * Laravel validation adapter for Dynamic Enum selection (Phase 5).
 *
 * Thin adapter around DynamicEnumValidator. Does not resolve options, canonicalize,
 * or enforce requiredness itself — those belong to the domain validator.
 *
 * Consumers must pass an explicit definition key (e.g. profile.gender, address.type).
 *
 * School context is explicit only:
 *   - Pass a School instance for school-scoped validation (validate against school overlay).
 *   - Pass null for intentional tenant/default validation (validateTenant).
 * There is no implicit GetSchoolModel() fallback. School-scoped FormRequests must
 * supply GetSchoolModel() (or another resolved School) as the constructor argument.
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
     * @param  School|null  $school  Explicit school for school-scoped resolution.
     *                               Null means intentional tenant/default validation only —
     *                               never silently derived from GetSchoolModel().
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

        /** @var DynamicEnumValidator $validator */
        $validator = app(DynamicEnumValidator::class);

        $result = $this->school === null
            ? $validator->validateTenant($this->key, $stringValue)
            : $validator->validate($this->school, $this->key, $stringValue);

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
