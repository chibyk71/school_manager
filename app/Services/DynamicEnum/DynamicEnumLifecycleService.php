<?php

/**
 * Dynamic Enum Phase 2R — option lifecycle & sparse school overlays.
 *
 * Definitions are application-owned (one key = one baseline definition).
 * Tenant options form the baseline vocabulary.
 * School options are sparse per-value overlays on the same definition:
 *   - same value as tenant → school override via createSchoolOverride()
 *   - value absent from tenant → school-only via createSchoolOption()
 *
 * Removing a school override deletes the school row (fallback to tenant).
 * Deactivation sets is_active = false and keeps the row.
 * Permanent deletion is separate and dependency-protected (fail closed for unknown keys).
 *
 * Phase 3R: option values are canonicalized (trim + lowercase) on write.
 * is_required is tenant-level only; school creates reject is_required = true.
 *
 * No complete school definition replacement. No option mass-copy. No resolution logic here.
 */

namespace App\Services\DynamicEnum;

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DynamicEnumLifecycleService
{
    private const PRESENTATION_FIELDS = ['label', 'sort_order', 'color', 'icon'];

    private const DEFINITION_PRESENTATION_FIELDS = ['label', 'description'];

    public function ensureDefinition(string $key, string $label, ?string $description = null): DynamicEnum
    {
        $existing = DynamicEnum::query()
            ->whereNull('school_id')
            ->where('key', $key)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createDefinition($key, $label, $description);
    }

    public function createDefinition(string $key, string $label, ?string $description = null): DynamicEnum
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
                'key' => "A definition for key [{$key}] already exists.",
            ]);
        }
    }

    public function updateDefinitionPresentation(DynamicEnum $definition, array $attributes): DynamicEnum
    {
        $this->assertApplicationDefinition($definition);

        $payload = array_intersect_key($attributes, array_flip(self::DEFINITION_PRESENTATION_FIELDS));

        if (array_key_exists('label', $payload)) {
            $this->assertNonEmptyLabel((string) $payload['label']);
        }

        $forbidden = array_diff_key($attributes, array_flip(self::DEFINITION_PRESENTATION_FIELDS));
        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                'definition' => 'Definition identity fields cannot be modified. Rejected: '.implode(', ', array_keys($forbidden)).'.',
            ]);
        }

        $definition->fill($payload);
        $definition->save();

        return $definition->refresh();
    }

    public function createTenantOption(
        DynamicEnum $definition,
        string $value,
        string $label,
        array $attributes = []
    ): DynamicEnumOption {
        $this->assertApplicationDefinition($definition);

        return $this->createOptionRow($definition, null, $value, $label, $attributes);
    }

    public function updateOptionPresentation(DynamicEnumOption $option, array $attributes): DynamicEnumOption
    {
        $payload = array_intersect_key($attributes, array_flip(self::PRESENTATION_FIELDS));

        if (array_key_exists('label', $payload)) {
            $this->assertNonEmptyLabel((string) $payload['label']);
        }

        $forbidden = array_diff_key($attributes, array_flip(self::PRESENTATION_FIELDS));
        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                'option' => 'Option identity and lifecycle flags cannot be modified here. Rejected: '.implode(', ', array_keys($forbidden)).'.',
            ]);
        }

        $option->fill($payload);
        $option->save();

        return $option->refresh();
    }

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

    public function activateOption(DynamicEnumOption $option): DynamicEnumOption
    {
        $option->is_active = true;
        $option->save();

        return $option->refresh();
    }

    public function restoreOption(DynamicEnumOption $option): DynamicEnumOption
    {
        return $this->activateOption($option);
    }

    public function makeOptionRequired(DynamicEnumOption $option): DynamicEnumOption
    {
        if (! $option->is_active) {
            throw ValidationException::withMessages([
                'is_required' => 'An inactive option must be activated before it can become required.',
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

    public function permanentlyDeleteTenantOption(DynamicEnumOption $option): void
    {
        if (! $option->isTenantOption()) {
            throw ValidationException::withMessages([
                'option' => 'Expected a tenant baseline option.',
            ]);
        }

        DB::transaction(function () use ($option) {
            $locked = DynamicEnumOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            $definition = $locked->dynamicEnum()->firstOrFail();

            $schoolOverlays = DynamicEnumOption::query()
                ->where('dynamic_enum_id', $locked->dynamic_enum_id)
                ->whereNotNull('school_id')
                ->where('value', $locked->value)
                ->count();

            if ($schoolOverlays > 0) {
                throw ValidationException::withMessages([
                    'option' => "Cannot delete tenant option [{$locked->value}]: school overlays still exist for this value.",
                ]);
            }

            $this->assertNoBusinessReferences($definition->key, $locked->value);

            $locked->delete();
        });
    }

    public function createSchoolOption(
        DynamicEnum $definition,
        School $school,
        string $value,
        string $label,
        array $attributes = []
    ): DynamicEnumOption {
        $this->assertApplicationDefinition($definition);
        $this->assertSchoolOptionRejectsRequired($attributes);

        $canonical = DynamicEnumValue::canonicalize($value);

        $tenantExists = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->whereNull('school_id')
            ->where('value', $canonical)
            ->exists();

        if ($tenantExists) {
            throw ValidationException::withMessages([
                'value' => "Tenant option [{$canonical}] already exists. Use createSchoolOverride() for school overrides of tenant values.",
            ]);
        }

        return $this->createOptionRow($definition, $school->id, $canonical, $label, $attributes);
    }

    public function createSchoolOverride(
        DynamicEnum $definition,
        School $school,
        string $value,
        string $label,
        array $attributes = []
    ): DynamicEnumOption {
        $this->assertApplicationDefinition($definition);
        $this->assertSchoolOptionRejectsRequired($attributes);

        $canonical = DynamicEnumValue::canonicalize($value);

        $tenant = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->whereNull('school_id')
            ->where('value', $canonical)
            ->first();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'value' => "No tenant option [{$canonical}] exists to override. Use createSchoolOption() for school-only values.",
            ]);
        }

        return $this->createOptionRow($definition, $school->id, $canonical, $label, $attributes);
    }

    public function removeSchoolOverride(DynamicEnumOption $option): void
    {
        if (! $option->isSchoolOption()) {
            throw ValidationException::withMessages([
                'option' => 'Expected a school-owned option to remove.',
            ]);
        }

        DB::transaction(function () use ($option) {
            $locked = DynamicEnumOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            $hasTenantFallback = DynamicEnumOption::query()
                ->where('dynamic_enum_id', $locked->dynamic_enum_id)
                ->whereNull('school_id')
                ->where('value', $locked->value)
                ->exists();

            if (! $hasTenantFallback) {
                $definition = $locked->dynamicEnum()->firstOrFail();
                $this->assertNoBusinessReferences($definition->key, $locked->value);
            }

            $locked->delete();
        });
    }

    public function permanentlyDeleteSchoolOption(DynamicEnumOption $option): void
    {
        if (! $option->isSchoolOption()) {
            throw ValidationException::withMessages([
                'option' => 'Expected a school-owned option.',
            ]);
        }

        DB::transaction(function () use ($option) {
            $locked = DynamicEnumOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            $definition = $locked->dynamicEnum()->firstOrFail();

            $hasTenantFallback = DynamicEnumOption::query()
                ->where('dynamic_enum_id', $locked->dynamic_enum_id)
                ->whereNull('school_id')
                ->where('value', $locked->value)
                ->exists();

            if ($hasTenantFallback) {
                $locked->delete();

                return;
            }

            $this->assertNoBusinessReferences($definition->key, $locked->value);
            $locked->delete();
        });
    }

    public function assertApplicationDefinition(DynamicEnum $definition): void
    {
        if ($definition->school_id !== null) {
            throw ValidationException::withMessages([
                'definition' => 'Expected an application/tenant definition (school_id must be null).',
            ]);
        }
    }

    public function assertSchoolOwnsOption(DynamicEnumOption $option, School $school): void
    {
        if ($option->school_id === null) {
            throw ValidationException::withMessages([
                'option' => 'Cannot perform school operations on a tenant option.',
            ]);
        }

        if ($option->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'option' => 'Option does not belong to the specified school.',
            ]);
        }
    }

    public function assertIsTenantOption(DynamicEnumOption $option): void
    {
        if (! $option->isTenantOption()) {
            throw ValidationException::withMessages([
                'option' => 'Expected a tenant baseline option.',
            ]);
        }
    }

    private function createOptionRow(
        DynamicEnum $definition,
        ?string $schoolId,
        string $value,
        string $label,
        array $attributes
    ): DynamicEnumOption {
        $this->assertNonEmptyValue($value);
        $this->assertNonEmptyLabel($label);

        // Canonical contract: stored value is trim + lowercase identity.
        $value = DynamicEnumValue::canonicalize($value);

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
                'school_id' => $schoolId,
                'value' => $value,
                'label' => $label,
                'sort_order' => $attributes['sort_order'] ?? 0,
                'is_active' => $isActive,
                'is_required' => $isRequired,
                'color' => $attributes['color'] ?? null,
                'icon' => $attributes['icon'] ?? null,
            ]);
        } catch (QueryException $e) {
            $scope = $schoolId === null ? 'tenant' : 'school';
            throw ValidationException::withMessages([
                'value' => "An option with value [{$value}] already exists in this {$scope} scope.",
            ]);
        }
    }

    private function assertNoBusinessReferences(string $key, string $value): void
    {
        // Fail closed: unknown keys have no dependency metadata — cannot safely delete.
        if (! DynamicEnumConsumerRegistry::isRegistered($key)) {
            throw ValidationException::withMessages([
                'option' => "Cannot permanently delete [{$value}]: Dynamic Enum key [{$key}] has no registered consumer metadata.",
            ]);
        }

        foreach (DynamicEnumConsumerRegistry::consumersFor($key) as $consumer) {
            $modelClass = $consumer['model'];
            $column = $consumer['column'];

            if (! class_exists($modelClass)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $modelClass;
            $table = $instance->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $exists = DB::table($table)->where($column, $value)->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'option' => "Cannot permanently delete [{$value}]: referenced by {$modelClass}.{$column}.",
                ]);
            }
        }
    }

    /**
     * is_required is tenant-level configuration only.
     * School overlays must not persist independent requiredness.
     */
    private function assertSchoolOptionRejectsRequired(array $attributes): void
    {
        if (array_key_exists('is_required', $attributes) && (bool) $attributes['is_required']) {
            throw ValidationException::withMessages([
                'is_required' => 'is_required is tenant-level configuration only; school options and overrides cannot set is_required.',
            ]);
        }
    }

    private function assertNonEmptyKey(string $key): void
    {
        if (trim($key) === '') {
            throw ValidationException::withMessages(['key' => 'Definition key is required.']);
        }
    }

    private function assertNonEmptyValue(string $value): void
    {
        if (trim($value) === '') {
            throw ValidationException::withMessages(['value' => 'Option value is required.']);
        }
    }

    private function assertNonEmptyLabel(string $label): void
    {
        if (trim($label) === '') {
            throw ValidationException::withMessages(['label' => 'Label is required.']);
        }
    }
}
