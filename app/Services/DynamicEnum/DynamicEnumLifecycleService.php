<?php

/**
 * Dynamic Enum Phase 2R — option lifecycle & sparse school overlays.
 *
 * Tenant options (school_id NULL) form the baseline.
 * School options (school_id set) are sparse overlays: overrides of tenant values
 * by canonical value, or school-only additions.
 *
 * value is immutable after create. is_required is tenant-level only.
 * Permanent deletion is dependency-protected via DynamicEnumConsumerRegistry.
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
        if (! $option->isTenantOption()) {
            throw ValidationException::withMessages([
                'is_required' => 'is_required is tenant-level configuration only; school options and overrides cannot set is_required.',
            ]);
        }

        $option->is_required = true;
        $option->is_active = true;
        $option->save();

        return $option->refresh();
    }

    public function removeOptionRequired(DynamicEnumOption $option): DynamicEnumOption
    {
        if (! $option->isTenantOption()) {
            throw ValidationException::withMessages([
                'is_required' => 'is_required is tenant-level configuration only.',
            ]);
        }

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

        return $this->createOptionRow($definition, $school->id, $value, $label, $attributes, allowOverride: $tenantExists);
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

    private function createOptionRow(
        DynamicEnum $definition,
        ?string $schoolId,
        string $value,
        string $label,
        array $attributes = [],
        bool $allowOverride = false,
    ): DynamicEnumOption {
        $canonical = DynamicEnumValue::canonicalize($value);

        if ($canonical === '') {
            throw ValidationException::withMessages([
                'value' => 'Option value cannot be empty after canonicalization.',
            ]);
        }

        $this->assertNonEmptyLabel($label);

        if ($schoolId === null) {
            $this->assertUniqueTenantValue($definition, $canonical);
        } else {
            $this->assertUniqueSchoolValue($definition, $schoolId, $canonical, $allowOverride);
        }

        if ($schoolId !== null) {
            $this->assertSchoolOptionRejectsRequired($attributes);
        }

        $payload = [
            'dynamic_enum_id' => $definition->id,
            'school_id' => $schoolId,
            'value' => $canonical,
            'label' => $label,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'is_required' => $schoolId === null ? (bool) ($attributes['is_required'] ?? false) : false,
            'color' => $attributes['color'] ?? null,
            'icon' => $attributes['icon'] ?? null,
        ];

        return DynamicEnumOption::query()->create($payload);
    }

    private function assertApplicationDefinition(DynamicEnum $definition): void
    {
        if ($definition->school_id !== null) {
            throw ValidationException::withMessages([
                'definition' => 'Options must be attached to an application (tenant) definition.',
            ]);
        }
    }

    private function assertUniqueTenantValue(DynamicEnum $definition, string $value): void
    {
        $exists = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->whereNull('school_id')
            ->where('value', $value)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'value' => "An option with value [{$value}] already exists in this tenant scope.",
            ]);
        }
    }

    private function assertUniqueSchoolValue(
        DynamicEnum $definition,
        string $schoolId,
        string $value,
        bool $allowOverride
    ): void {
        $exists = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->where('school_id', $schoolId)
            ->where('value', $value)
            ->exists();

        if ($exists) {
            $scope = $allowOverride ? 'school' : 'school';
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

    private function assertNonEmptyLabel(string $label): void
    {
        if (trim($label) === '') {
            throw ValidationException::withMessages(['label' => 'Option label is required.']);
        }
    }
}
