<?php

/**
 * Dynamic Enum Phase 3R — effective resolution (sparse overlay merge).
 *
 * Algorithm:
 *   1. Load application-owned definition by key (fail if missing)
 *   2. Optionally apply school definition presentation (label/description only)
 *   3. Load tenant baseline options
 *   4. Load school overlay options when school context is provided
 *   5. Merge by canonical value (school wins when present)
 *   6. Apply tenant-required enforcement as derived is_active only
 *   7. Order by sort_order ASC, value ASC
 *
 * Read-only: never creates, updates, deletes, or activates configuration rows.
 * No caching, snapshots, or materialized effective tables.
 */

namespace App\Services\DynamicEnum;

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DynamicEnumResolver
{
    /**
     * Resolve effective configuration for the tenant/default (no school overlay).
     *
     * @throws DynamicEnumNotConfiguredException
     */
    public function resolve(string $key): ResolvedDynamicEnum
    {
        return $this->resolveInternal($key, null);
    }

    /**
     * Resolve effective configuration for an explicit school context.
     *
     * @throws DynamicEnumNotConfiguredException
     * @throws InvalidArgumentException when the school is unsaved / has no id
     */
    public function resolveForSchool(School $school, string $key): ResolvedDynamicEnum
    {
        $this->assertPersistedSchool($school);

        return $this->resolveInternal($key, $school);
    }

    /**
     * Backward-compatible alias used by Phase 3 validator callers that pass school first.
     *
     * @throws DynamicEnumNotConfiguredException
     */
    public function resolveWithOptions(School $school, string $key): ResolvedDynamicEnum
    {
        return $this->resolveForSchool($school, $key);
    }

    /**
     * @throws DynamicEnumNotConfiguredException
     */
    private function resolveInternal(string $key, ?School $school): ResolvedDynamicEnum
    {
        $key = trim($key);

        if ($key === '') {
            throw new DynamicEnumNotConfiguredException($key);
        }

        $definition = DynamicEnum::query()
            ->whereNull('school_id')
            ->where('key', $key)
            ->first();

        if ($definition === null) {
            throw new DynamicEnumNotConfiguredException($key);
        }

        $label = $definition->label;
        $description = $definition->description;

        // Definition presentation override (school row for same key) — presentation only.
        if ($school !== null) {
            $schoolPresentation = DynamicEnum::query()
                ->where('school_id', $school->id)
                ->where('key', $key)
                ->first();

            if ($schoolPresentation !== null) {
                $label = $schoolPresentation->label ?? $label;
                $description = $schoolPresentation->description ?? $description;
            }
        }

        $tenantOptions = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->whereNull('school_id')
            ->get();

        $schoolOptions = $school === null
            ? collect()
            : DynamicEnumOption::query()
                ->where('dynamic_enum_id', $definition->id)
                ->where('school_id', $school->id)
                ->get();

        $merged = $this->mergeOptions($tenantOptions, $schoolOptions);

        return new ResolvedDynamicEnum(
            key: $definition->key,
            label: $label,
            description: $description,
            options: $merged,
            definitionId: $definition->id,
        );
    }

    /**
     * Merge tenant baseline + school overlay by canonical value.
     *
     * @param  Collection<int, DynamicEnumOption>  $tenantOptions
     * @param  Collection<int, DynamicEnumOption>  $schoolOptions
     * @return Collection<int, ResolvedDynamicEnumOption>
     */
    private function mergeOptions(Collection $tenantOptions, Collection $schoolOptions): Collection
    {
        /** @var array<string, DynamicEnumOption> $tenantByValue */
        $tenantByValue = [];
        foreach ($tenantOptions as $option) {
            $canonical = DynamicEnumValue::canonicalize((string) $option->value);
            if ($canonical === '') {
                continue;
            }
            $tenantByValue[$canonical] = $option;
        }

        /** @var array<string, DynamicEnumOption> $schoolByValue */
        $schoolByValue = [];
        foreach ($schoolOptions as $option) {
            $canonical = DynamicEnumValue::canonicalize((string) $option->value);
            if ($canonical === '') {
                continue;
            }
            $schoolByValue[$canonical] = $option;
        }

        $allValues = array_unique(array_merge(array_keys($tenantByValue), array_keys($schoolByValue)));

        $resolved = [];

        foreach ($allValues as $canonical) {
            $tenant = $tenantByValue[$canonical] ?? null;
            $school = $schoolByValue[$canonical] ?? null;

            if ($school !== null && $tenant !== null) {
                $isRequired = (bool) $tenant->is_required;
                $schoolActive = (bool) $school->is_active;
                $enforced = false;
                $enforcementReason = null;
                $effectiveActive = $schoolActive;

                if ($isRequired && ! $schoolActive) {
                    $effectiveActive = true;
                    $enforced = true;
                    $enforcementReason = ResolvedDynamicEnumOption::ENFORCEMENT_TENANT_REQUIRED;
                }

                $resolved[] = new ResolvedDynamicEnumOption(
                    value: $canonical,
                    label: (string) $school->label,
                    sortOrder: (int) $school->sort_order,
                    isActive: $effectiveActive,
                    isRequired: $isRequired,
                    color: $school->color,
                    icon: $school->icon,
                    source: ResolvedDynamicEnumOption::SOURCE_SCHOOL,
                    overridden: true,
                    enforced: $enforced,
                    enforcementReason: $enforcementReason,
                );
            } elseif ($school !== null) {
                $resolved[] = new ResolvedDynamicEnumOption(
                    value: $canonical,
                    label: (string) $school->label,
                    sortOrder: (int) $school->sort_order,
                    isActive: (bool) $school->is_active,
                    isRequired: false,
                    color: $school->color,
                    icon: $school->icon,
                    source: ResolvedDynamicEnumOption::SOURCE_SCHOOL,
                    overridden: false,
                    enforced: false,
                    enforcementReason: null,
                );
            } else {
                $resolved[] = new ResolvedDynamicEnumOption(
                    value: $canonical,
                    label: (string) $tenant->label,
                    sortOrder: (int) $tenant->sort_order,
                    isActive: (bool) $tenant->is_active,
                    isRequired: (bool) $tenant->is_required,
                    color: $tenant->color,
                    icon: $tenant->icon,
                    source: ResolvedDynamicEnumOption::SOURCE_TENANT,
                    overridden: false,
                    enforced: false,
                    enforcementReason: null,
                );
            }
        }

        usort($resolved, function (ResolvedDynamicEnumOption $a, ResolvedDynamicEnumOption $b): int {
            if ($a->sortOrder !== $b->sortOrder) {
                return $a->sortOrder <=> $b->sortOrder;
            }

            return $a->value <=> $b->value;
        });

        return collect(array_values($resolved));
    }

    private function assertPersistedSchool(School $school): void
    {
        if ($school->id === null || $school->id === '') {
            throw new InvalidArgumentException(
                'School context must be a persisted school with an id; unsaved schools are not allowed.'
            );
        }
    }
}
