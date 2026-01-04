<?php
/**
 * app/Rules/InDynamicEnum.php
 *
 * Custom Laravel validation rule that enforces "enum-like" strictness for dynamic enum properties.
 *
 * Features / Problems Solved:
 * - Validates that a submitted value belongs to the currently allowed options for a dynamic enum
 *   property on a specific model, fully respecting multi-tenant scoping (school-specific overrides
 *   take precedence over global defaults).
 * - Mimics the behavior of a native PHP/Laravel enum validation rule (e.g., Rule::in([...])) but
 *   with options fetched dynamically from the database at validation time.
 * - Handles nullable values gracefully (passes if value is null/empty).
 * - Fails early with a clear, user-friendly message if:
 *     • No dynamic enum definition exists for the property (prevents silent invalid data).
 *     • The submitted value is not in the allowed list.
 * - Single-query lookup using the efficient visibleToSchool() + forModel() scopes.
 * - Fully compatible with Form Requests, Inertia validation, and manual Validator usage.
 * - Implicit rule (fails even if field is not required) – appropriate for enum-style fields.
 *
 * Fits into the DynamicEnums Module:
 * - Provides server-side enforcement of allowed values, complementing the client-side dropdown/radio
 *   components and the HasDynamicEnum trait's setDynamicEnumValue() validation.
 * - Used in Store/Update requests for models that have dynamic enum columns
 *   (e.g., ProfileRequest: 'gender' => ['required', new InDynamicEnum('gender', Profile::class)]).
 * - Ensures data integrity: even if frontend is bypassed, invalid values are rejected.
 * - Works seamlessly with the current school context via GetSchoolModel() helper.
 * - Critical for production safety before CRUD controllers process submissions.
 */

namespace App\Rules;

use App\Models\DynamicEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InDynamicEnum implements ValidationRule
{
    /**
     * Indicates whether the rule should continue validation even if it fails.
     * Set to true for enum-style strictness.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * The dynamic enum property name (e.g., 'gender', 'title').
     */
    protected string $property;

    /**
     * The fully qualified model class the property belongs to (e.g., App\Models\Profile::class).
     */
    protected string $modelClass;

    /**
     * Create a new rule instance.
     *
     * @param string $property   The column/property name that is a dynamic enum.
     * @param string $modelClass The model class the property belongs to.
     */
    public function __construct(string $property, string $modelClass)
    {
        $this->property   = $property;
        $this->modelClass = $modelClass;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The field under validation (e.g., 'gender').
     * @param  mixed   $value      The submitted value.
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Allow null/empty values (common for optional fields or soft deletes)
        if ($value === null || $value === '') {
            return;
        }

        // Determine current school context for tenant scoping
        $school = GetSchoolModel();
        $schoolId = $school?->id;

        // Fetch the effective dynamic enum definition (school override or global default)
        $enum = DynamicEnum::visibleToSchool($schoolId)
            ->forModel($this->modelClass)
            ->where('name', $this->property)
            ->first();

        // If no definition exists, fail – prevents invalid data due to misconfiguration
        if (!$enum) {
            $fail("The dynamic enum for :attribute is not configured.");
            return;
        }

        // Extract allowed values from the options array
        $allowedValues = array_column($enum->options ?? [], 'value');

        // Check if submitted value is in the allowed list (strict comparison)
        if (!in_array($value, $allowedValues, true)) {
            $fail("The selected :attribute is invalid. Allowed values are: " . implode(', ', $allowedValues) . ".");
        }
    }
}
