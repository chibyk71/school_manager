<?php
/**
 * app/Traits/HasDynamicEnum.php
 *
 * Trait that enables Eloquent models to use dynamic, tenant-customizable "enum" option lists.
 *
 * Features / Problems Solved:
 * - Allows models (e.g., Profile, Address) to declare properties that behave like enums
 *   but whose options can be customized per school (tenant).
 * - Automatically merges global (system-wide) defaults with school-specific overrides.
 * - Provides safe value storage directly on the model (no pivot table needed).
 * - Enforces validation at assignment time (invalid values throw an exception).
 * - Centralizes option fetching for forms (dropdowns, radios) via getVisibleEnums().
 * - Reuses the existing DynamicEnum model and its scopes (visibleToSchool, forModel).
 * - Mirrors the API pattern of your existing HasConfig trait for familiarity and consistency.
 * - Handles school context automatically via GetSchoolModel() helper.
 *
 * Fits into the DynamicEnums Module:
 * - This is the primary integration point for any resource model that needs dynamic enums.
 * - Models simply use the trait and implement getDynamicEnumProperties() to list their enum fields.
 * - Example: Profile model will return ['title', 'gender', 'profile_type'].
 * - Works with the upcoming InDynamicEnum validation rule and frontend composable.
 * - Keeps value storage simple (column on the model table) while definition is dynamic.
 * - Fully multi-tenant aware: options respect the current school, falling back to global defaults.
 */

namespace App\Traits;

use App\Models\DynamicEnum;
use App\Models\School;
use Illuminate\Support\Facades\Log;

trait HasDynamicEnum
{
    /**
     * Abstract method – must be implemented by the model using this trait.
     *
     * @return array<string> List of column names that are dynamic enums.
     */
    abstract public function getDynamicEnumProperties(): array;

    /**
     * Add or update a dynamic enum definition (admin use case).
     *
     * @param string $name   Machine name (e.g., 'gender')
     * @param string $label  UI label
     * @param array  $options [{value: string, label: string, color?: string}, ...]
     * @return DynamicEnum
     */
    public function addDynamicEnum(string $name, string $label, array $options = []): DynamicEnum
    {
        try {
            $school = GetSchoolModel() ?? $this; // Fallback to $this if $this is the School model

            return DynamicEnum::updateOrCreate(
                [
                    'name' => $name,
                    'applies_to' => static::class,
                    'school_id' => $school?->id,
                ],
                [
                    'label' => $label,
                    'options' => $options,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Failed to add dynamic enum '{$name}' for " . static::class . ": " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch all visible dynamic enums for this model's dynamic properties.
     *
     * Returns a collection of DynamicEnum models, keyed by name, with highest priority
     * (school-specific) overriding global defaults.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getVisibleEnums()
    {
        $school = GetSchoolModel() ?? null;

        $properties = $this->getDynamicEnumProperties();

        return DynamicEnum::visibleToSchool($school?->id)
            ->forModel(static::class)
            ->whereIn('name', $properties)
            ->get()
            ->keyBy('name');
    }

    /**
     * Get the allowed options array for a specific dynamic enum property.
     *
     * @param string $name Property name (e.g., 'gender')
     * @return array Empty array if no definition found
     */
    public function getDynamicEnumOptions(string $name): array
    {
        $enums = $this->getVisibleEnums();

        return $enums->has($name) ? $enums[$name]->options : [];
    }

    /**
     * Set a dynamic enum value on the model with validation.
     *
     * @param string $name  Property name
     * @param mixed  $value Value to store
     * @return void
     * @throws \Exception If value is not in the allowed options
     */
    public function setDynamicEnumValue(string $name, $value): void
    {
        if (!in_array($name, $this->getDynamicEnumProperties(), true)) {
            throw new \InvalidArgumentException("Property '{$name}' is not a dynamic enum on " . static::class);
        }

        $options = $this->getDynamicEnumOptions($name);

        // Allow null/empty if column is nullable
        if ($value === null || $value === '') {
            $this->attributes[$name] = null;
            return;
        }

        $allowedValues = array_column($options, 'value');

        if (!in_array($value, $allowedValues, true)) {
            throw new \InvalidArgumentException(
                "Invalid value '{$value}' for dynamic enum '{$name}' on " . static::class .
                ". Allowed values: " . implode(', ', $allowedValues)
            );
        }

        $this->attributes[$name] = $value;
    }

    /**
     * Get the current value of a dynamic enum property (proxy to attribute).
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function getDynamicEnumValue(string $name, $default = null)
    {
        return $this->getAttribute($name) ?? $default;
    }

    /**
     * Optional: Magic method to allow $model->gender = 'male' with validation.
     */
    public function __set($key, $value)
    {
        if (in_array($key, $this->getDynamicEnumProperties(), true)) {
            $this->setDynamicEnumValue($key, $value);
            return;
        }

        parent::__set($key, $value);
    }
}
