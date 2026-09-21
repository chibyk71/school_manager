<?php

/**
 * Dynamic Enum Phase 2 — definition & option lifecycle.
 *
 * Explicit domain operations for:
 *   - definition create / presentation update (identity immutable)
 *   - option create / presentation update (value + parent immutable)
 *   - activate / deactivate / restore
 *   - make required / optional
 *   - school customization create / update / revert
 *
 * Ownership boundaries are enforced here. HTTP authorization is Phase 4.
 * Effective resolution and selected-value validation are Phase 3.
 *
 * No synchronization/propagation between tenant and school definitions.
 */

namespace App\Services\DynamicEnum;

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DynamicEnumLifecycleService
{
    private const PRESENTATION_FIELDS = ['label', 'sort_order', 'color', 'icon'];

    private const DEFINITION_PRESENTATION_FIELDS = ['label', 'description'];

    /* -----------------------------------------------------------------
     | Definitions
     | ----------------------------------------------------------------- */

    public function createDefaultDefinition(string $key, string $label, ?string $description = null): DynamicEnum
    {
        $this->assertNonEmptyKey($key);
        $this->assertNonEmptyLabel($label);

        try {
            return DynamicEnum::create([
                'school_id' => null,
                'key' => $key,
                'label' => $label,
                'description' => $description,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'key' => "A default definition for key [{$key}] already exists.",
            ]);
        }
    }

    public function createSchoolDefinition(School $school, string $key, string $label, ?string $description = null): DynamicEnum
    {
        $this->assertNonEmptyKey($key);
        $this->assertNonEmptyLabel($label);

        try {
            return DynamicEnum::create([
                'school_id' => $school->id,
                'key' => $key,
                'label' => $label,
                'description' => $description,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'key' => "A definition for key [{$key}] already exists for this school.",
            ]);
        }
    }

    /**
     * Update only mutable presentation fields (label, description).
     * key and school_id are immutable.
     */
    public function updateDefinitionPresentation(DynamicEnum $definition, array $attributes): DynamicEnum
    {
        $payload = array_intersect_key($attributes, array_flip(self::DEFINITION_PRESENTATION_FIELDS));

        if (array_key_exists('label', $payload)) {
            $this->assertNonEmptyLabel((string) $payload['label']);
        }

        $forbidden = array_diff_key($attributes, array_flip(self::DEFINITION_PRESENTATION_FIELDS));
        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                'definition' => 'Definition identity fields (id, school_id, key) cannot be modified. Rejected: '.implode(', ', array_keys($forbidden)).'.',
            ]);
        }

        $definition->fill($payload);
        $definition->save();

        return $definition->refresh();
    }

    /* -----------------------------------------------------------------
     | Options — identity & presentation
     | ----------------------------------------------------------------- */

    /**
     * @param  array{label?: string, sort_order?: int, is_active?: bool, is_required?: bool, color?: string|null, icon?: string|null}  $attributes
     */
    public function createOption(DynamicEnum $definition, string $value, string $label, array $attributes = []): DynamicEnumOption
    {
        $this->assertNonEmptyValue($value);
        $this->assertNonEmptyLabel($label);

        $isActive = array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true;
        $isRequired = array_key_exists('is_required', $attributes) ? (bool) $attributes['is_required'] : false;

        if ($isRequired && ! $isActive) {
            throw ValidationException::withMessages([
                'is_required' => 'A required option must be active.',
            ]);
        }

        try {
            return DynamicEnumOption::create([
                'dynamic_enum_id' => $definition->id,
                'value' => $value,
                'label' => $label,
                'sort_order' => $attributes['sort_order'] ?? 0,
                'is_active' => $isActive,
                'is_required' => $isRequired,
                'color' => $attributes['color'] ?? null,
                'icon' => $attributes['icon'] ?? null,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'value' => "An option with value [{$value}] already exists on this definition.",
            ]);
        }
    }

    /**
     * Update only presentation fields. value and dynamic_enum_id are immutable.
     *
     * @param  array{label?: string, sort_order?: int, color?: string|null, icon?: string|null}  $attributes
     */
    public function updateOptionPresentation(DynamicEnumOption $option, array $attributes): DynamicEnumOption
    {
        $payload = array_intersect_key($attributes, array_flip(self::PRESENTATION_FIELDS));

        if (array_key_exists('label', $payload)) {
            $this->assertNonEmptyLabel((string) $payload['label']);
        }

        $forbidden = array_diff_key($attributes, array_flip(self::PRESENTATION_FIELDS));
        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                'option' => 'Option identity fields (id, dynamic_enum_id, value) and lifecycle flags cannot be modified here. Rejected: '.implode(', ', array_keys($forbidden)).'.',
            ]);
        }

        $option->fill($payload);
        $option->save();

        return $option->refresh();
    }

    /* -----------------------------------------------------------------
     | Option lifecycle — active / inactive / required
     | ----------------------------------------------------------------- */

    public function deactivateOption(DynamicEnumOption $option): DynamicEnumOption
    {
        if ($option->is_required) {
            throw ValidationException::withMessages([
                'is_active' => 'A required option cannot be deactivated. Make it optional first.',
            ]);
        }

        $option->is_active = false;
        $option->save();

        return $option->refresh();
    }

    public function restoreOption(DynamicEnumOption $option): DynamicEnumOption
    {
        $option->is_active = true;
        $option->save();

        return $option->refresh();
    }

    /** Alias of restore for callers that prefer activate wording. */
    public function activateOption(DynamicEnumOption $option): DynamicEnumOption
    {
        return $this->restoreOption($option);
    }

    public function makeOptionRequired(DynamicEnumOption $option): DynamicEnumOption
    {
        if (! $option->is_active) {
            throw ValidationException::withMessages([
                'is_required' => 'An inactive option must be restored before it can become required.',
            ]);
        }

        $option->is_required = true;
        $option->save();

        return $option->refresh();
    }

    public function makeOptionOptional(DynamicEnumOption $option): DynamicEnumOption
    {
        $option->is_required = false;
        $option->save();

        return $option->refresh();
    }

    /* -----------------------------------------------------------------
     | School customization — complete replacement definition
     | ----------------------------------------------------------------- */

    /**
     * Create a school-owned complete replacement definition for a key.
     *
     * @param  array<int, array{value: string, label: string, sort_order?: int, is_active?: bool, is_required?: bool, color?: string|null, icon?: string|null}>  $options
     */
    public function createSchoolCustomization(
        School $school,
        string $key,
        string $label,
        array $options,
        ?string $description = null
    ): DynamicEnum {
        $this->assertNonEmptyKey($key);
        $this->assertNonEmptyLabel($label);
        $this->assertCustomizationPreservesRequiredTenantOptions($key, $options);

        return DB::transaction(function () use ($school, $key, $label, $options, $description) {
            $definition = $this->createSchoolDefinition($school, $key, $label, $description);

            foreach ($options as $index => $optionData) {
                $this->assertOptionPayload($optionData, $index);
                $this->createOption(
                    $definition,
                    $optionData['value'],
                    $optionData['label'],
                    [
                        'sort_order' => $optionData['sort_order'] ?? $index,
                        'is_active' => $optionData['is_active'] ?? true,
                        'is_required' => $optionData['is_required'] ?? false,
                        'color' => $optionData['color'] ?? null,
                        'icon' => $optionData['icon'] ?? null,
                    ]
                );
            }

            return $definition->load('options');
        });
    }

    /**
     * Replace the full option set on an existing school customization.
     * Presentation of the definition may also be updated.
     *
     * @param  array{label?: string, description?: string|null}  $presentation
     * @param  array<int, array{value: string, label: string, sort_order?: int, is_active?: bool, is_required?: bool, color?: string|null, icon?: string|null}>  $options
     */
    public function updateSchoolCustomization(
        DynamicEnum $schoolDefinition,
        array $presentation,
        array $options
    ): DynamicEnum {
        $this->assertIsSchoolDefinition($schoolDefinition);
        $this->assertCustomizationPreservesRequiredTenantOptions($schoolDefinition->key, $options);

        return DB::transaction(function () use ($schoolDefinition, $presentation, $options) {
            if ($presentation !== []) {
                $this->updateDefinitionPresentation($schoolDefinition, $presentation);
            }

            // Full replacement: remove existing options, recreate from payload.
            // Physical delete here is configuration replacement, not option lifecycle.
            $schoolDefinition->options()->delete();

            foreach ($options as $index => $optionData) {
                $this->assertOptionPayload($optionData, $index);
                $this->createOption(
                    $schoolDefinition,
                    $optionData['value'],
                    $optionData['label'],
                    [
                        'sort_order' => $optionData['sort_order'] ?? $index,
                        'is_active' => $optionData['is_active'] ?? true,
                        'is_required' => $optionData['is_required'] ?? false,
                        'color' => $optionData['color'] ?? null,
                        'icon' => $optionData['icon'] ?? null,
                    ]
                );
            }

            return $schoolDefinition->refresh()->load('options');
        });
    }

    /**
     * Remove a school-owned definition and its options. Tenant definition is untouched.
     */
    public function revertSchoolCustomization(DynamicEnum $schoolDefinition): void
    {
        $this->assertIsSchoolDefinition($schoolDefinition);

        DB::transaction(function () use ($schoolDefinition) {
            // Cascade deletes options via FK.
            $schoolDefinition->delete();
        });
    }

    /* -----------------------------------------------------------------
     | Ownership guards (domain-level; policies are Phase 4)
     | ----------------------------------------------------------------- */

    public function assertOwnedBySchool(DynamicEnum $definition, School $school): void
    {
        if ($definition->school_id === null) {
            throw ValidationException::withMessages([
                'definition' => 'Cannot perform school-owned operations on a tenant/default definition.',
            ]);
        }

        if ($definition->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'definition' => 'Definition does not belong to the specified school.',
            ]);
        }
    }

    public function assertIsDefaultDefinition(DynamicEnum $definition): void
    {
        if ($definition->school_id !== null) {
            throw ValidationException::withMessages([
                'definition' => 'Expected a tenant/default definition (school_id must be null).',
            ]);
        }
    }

    public function assertIsSchoolDefinition(DynamicEnum $definition): void
    {
        if ($definition->school_id === null) {
            throw ValidationException::withMessages([
                'definition' => 'Expected a school-owned definition.',
            ]);
        }
    }

    public function assertOptionBelongsToDefinition(DynamicEnumOption $option, DynamicEnum $definition): void
    {
        if ($option->dynamic_enum_id !== $definition->id) {
            throw ValidationException::withMessages([
                'option' => 'Option does not belong to the specified definition.',
            ]);
        }
    }

    /* -----------------------------------------------------------------
     | Required-tenant-option preservation
     | ----------------------------------------------------------------- */

    /**
     * @param  array<int, array{value: string, label?: string, is_active?: bool, is_required?: bool}>  $options
     */
    private function assertCustomizationPreservesRequiredTenantOptions(string $key, array $options): void
    {
        $default = DynamicEnum::query()
            ->whereNull('school_id')
            ->where('key', $key)
            ->with('options')
            ->first();

        if ($default === null) {
            // No tenant definition yet — school may create a standalone customization.
            return;
        }

        $requiredValues = $default->options
            ->where('is_required', true)
            ->pluck('value')
            ->all();

        if ($requiredValues === []) {
            return;
        }

        $byValue = collect($options)->keyBy('value');

        foreach ($requiredValues as $requiredValue) {
            if (! $byValue->has($requiredValue)) {
                throw ValidationException::withMessages([
                    'options' => "School customization must preserve required tenant option [{$requiredValue}].",
                ]);
            }

            $schoolOption = $byValue->get($requiredValue);

            if (array_key_exists('is_active', $schoolOption) && $schoolOption['is_active'] === false) {
                throw ValidationException::withMessages([
                    'options' => "Required tenant option [{$requiredValue}] cannot be deactivated in a school customization.",
                ]);
            }
        }
    }

    /* -----------------------------------------------------------------
     | Input assertions
     | ----------------------------------------------------------------- */

    private function assertNonEmptyKey(string $key): void
    {
        if (trim($key) === '') {
            throw ValidationException::withMessages([
                'key' => 'Definition key is required.',
            ]);
        }
    }

    private function assertNonEmptyValue(string $value): void
    {
        if (trim($value) === '') {
            throw ValidationException::withMessages([
                'value' => 'Option value is required.',
            ]);
        }
    }

    private function assertNonEmptyLabel(string $label): void
    {
        if (trim($label) === '') {
            throw ValidationException::withMessages([
                'label' => 'Label is required.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $optionData
     */
    private function assertOptionPayload(array $optionData, int $index): void
    {
        if (! isset($optionData['value']) || ! is_string($optionData['value']) || trim($optionData['value']) === '') {
            throw ValidationException::withMessages([
                "options.{$index}.value" => 'Each option requires a non-empty value.',
            ]);
        }

        if (! isset($optionData['label']) || ! is_string($optionData['label']) || trim($optionData['label']) === '') {
            throw ValidationException::withMessages([
                "options.{$index}.label" => 'Each option requires a non-empty label.',
            ]);
        }

        $isActive = array_key_exists('is_active', $optionData) ? (bool) $optionData['is_active'] : true;
        $isRequired = array_key_exists('is_required', $optionData) ? (bool) $optionData['is_required'] : false;

        if ($isRequired && ! $isActive) {
            throw ValidationException::withMessages([
                "options.{$index}" => "Option [{$optionData['value']}] cannot be required and inactive.",
            ]);
        }
    }
}
