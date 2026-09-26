<?php

/**
 * Dynamic Enum Phase 2R — option lifecycle & sparse school overlays.
 *
 * Responsibilities:
 *   - Create application definitions (key identity)
 *   - Create tenant baseline options (school_id NULL)
 *   - Create school overlays: school-only options OR overrides of tenant values
 *   - Presentation updates (label, sort_order, color, icon)
 *   - Activation / deactivation / requiredness (tenant only for required)
 *   - Permanent deletion with dependency protection
 *
 * School overlay rules:
 *   - same value as tenant → school override via createSchoolOverride()
 *   - new value → school-only option via createSchoolOption()
 *   - is_required is tenant-level only; school rows must not set is_required true
 *
 * value is immutable after create. Canonicalization: trim + lowercase.
 */

namespace App\Services\DynamicEnum;

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DynamicEnumLifecycleService
{
    private const PRESENTATION_FIELDS = ['label', 'sort_order', 'color', 'icon'];

    public function ensureDefinition(string $key, string $label, ?string $description = null): DynamicEnum
    {
        $this->assertNonEmptyKey($key);

        $existing = DynamicEnum::query()
            ->whereNull('school_id')
            ->where('key', $key)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createDefinition($key, $label, $description);
    }

    public function createDefinition(string $key, string $label, ?string $description = null): DynamicEnum
    {
        $this->assertNonEmptyKey($key);
        $this->assertNonEmptyLabel($label);

        return DynamicEnum::query()->create([
            'school_id' => null,
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ]);
    }

    public function updateDefinitionPresentation(DynamicEnum $definition, array $attributes): DynamicEnum
    {
        $payload = array_intersect_key($attributes, array_flip(['label', 'description']));

        if (array_key_exists('label', $payload)) {
            $this->assertNonEmptyLabel((string) $payload['label']);
        }

        $forbidden = array_diff_key($attributes, array_flip(['label', 'description']));
        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                'definition' => 'Definition identity fields cannot be modified here. Rejected: '.implode(', ', array_keys($forbidden)).'.',
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
        $this->assertIsTenantOption($option);

        if (! $option->is_active) {
            throw ValidationException::withMessages([
                'option' => 'Inactive options cannot be made required. Restore the option first.',
            ]);
        }

        $option->is_required = true;
        $option->is_active = true;
        $option->save();

        return $option->refresh();
    }

    public function makeOptionOptional(DynamicEnumOption $option): DynamicEnumOption
    {
        $this->assertIsTenantOption($option);

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

        return $this->createOptionRow($definition, $school->id, $value, $label, $attributes);
    }

    public function createSchoolOverride(
        DynamicEnum $definition,
        School $school,
        string $tenantValue,
        string $label,
        array $attributes = []
    ): DynamicEnumOption {
        $this->assertApplicationDefinition($definition);
        $this->assertSchoolOptionRejectsRequired($attributes);

        $canonical = DynamicEnumValue::canonicalize($tenantValue);

        $tenantOption = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->whereNull('school_id')
            ->where('value', $canonical)
            ->first();

        if ($tenantOption === null) {
            throw ValidationException::withMessages([
                'value' => "No tenant option [{$canonical}] to override. Use createSchoolOption() for school-only values.",
            ]);
        }

        return $this->createOptionRow($definition, $school->id, $tenantValue, $label, $attributes);
    }

    public function removeSchoolOverride(DynamicEnumOption $option): void
    {
        if ($option->isTenantOption()) {
            throw ValidationException::withMessages([
                'option' => 'Expected a school overlay option.',
            ]);
        }

        DB::transaction(function () use ($option) {
            $locked = DynamicEnumOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            $definition = $locked->dynamicEnum()->firstOrFail();

            // Soft removal of overlay: delete the school row so tenant baseline reappears.
            // When a tenant baseline still exists for this value, business rows remain valid
            // via the tenant option — do not block overlay removal on dependency checks.
            // School-only values (no tenant row) must still pass dependency protection.
            $tenantBaselineExists = DynamicEnumOption::query()
                ->where('dynamic_enum_id', $locked->dynamic_enum_id)
                ->whereNull('school_id')
                ->where('value', $locked->value)
                ->exists();

            if (! $tenantBaselineExists) {
                $this->assertNoBusinessReferences($definition->key, $locked->value);
            }

            $locked->delete();
        });
    }

    public function permanentlyDeleteSchoolOption(DynamicEnumOption $option): void
    {
        if ($option->isTenantOption()) {
            throw ValidationException::withMessages([
                'option' => 'Expected a school overlay or school-only option.',
            ]);
        }

        DB::transaction(function () use ($option) {
            $locked = DynamicEnumOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            $definition = $locked->dynamicEnum()->firstOrFail();

            $this->assertNoBusinessReferences($definition->key, $locked->value);

            $locked->delete();
        });
    }

    public function assertApplicationDefinition(DynamicEnum $definition): void
    {
        if ($definition->school_id !== null) {
            throw ValidationException::withMessages([
                'definition' => 'Options must be attached to an application (tenant) definition.',
            ]);
        }
    }

    public function assertSchoolOwnsOption(DynamicEnumOption $option, School $school): void
    {
        if ($option->school_id === null || $option->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'option' => 'Option does not belong to the authorized school.',
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
        array $attributes = []
    ): DynamicEnumOption {
        $canonical = DynamicEnumValue::canonicalize($value);

        if ($canonical === '') {
            throw ValidationException::withMessages([
                'value' => 'Option value cannot be empty after canonicalization.',
            ]);
        }

        $this->assertNonEmptyLabel($label);

        $exists = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->when(
                $schoolId === null,
                fn ($q) => $q->whereNull('school_id'),
                fn ($q) => $q->where('school_id', $schoolId)
            )
            ->where('value', $canonical)
            ->exists();

        if ($exists) {
            $scope = $schoolId === null ? 'tenant' : 'school';
            throw ValidationException::withMessages([
                'value' => "An option with value [{$canonical}] already exists in this {$scope} scope.",
            ]);
        }

        if ($schoolId !== null) {
            $this->assertSchoolOptionRejectsRequired($attributes);
        }

        return DynamicEnumOption::query()->create([
            'dynamic_enum_id' => $definition->id,
            'school_id' => $schoolId,
            'value' => $canonical,
            'label' => $label,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'is_required' => $schoolId === null ? (bool) ($attributes['is_required'] ?? false) : false,
            'color' => $attributes['color'] ?? null,
            'icon' => $attributes['icon'] ?? null,
        ]);
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
            $column = $consumer['column'];
            $table = $consumer['table'] ?? null;
            $label = $table !== null ? "{$table}.{$column}" : null;

            if ($table === null && isset($consumer['model'])) {
                $modelClass = $consumer['model'];
                if (! class_exists($modelClass)) {
                    continue;
                }
                /** @var \Illuminate\Database\Eloquent\Model $instance */
                $instance = new $modelClass;
                $table = $instance->getTable();
                $label = "{$modelClass}.{$column}";
            }

            if ($table === null || $label === null) {
                continue;
            }

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $exists = DB::table($table)->where($column, $value)->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'option' => "Cannot permanently delete [{$value}]: referenced by {$label}.",
                ]);
            }
        }
    }

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
            throw ValidationException::withMessages(['label' => 'Option label is required.']);
        }
    }
}
