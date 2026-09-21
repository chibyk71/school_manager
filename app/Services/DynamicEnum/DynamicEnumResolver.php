<?php

/**
 * Dynamic Enum Phase 3 — effective definition resolution.
 *
 * Given an explicit school and a stable key, returns the effective DynamicEnum:
 *   1. School-owned definition for (school, key), if present (complete replacement)
 *   2. Otherwise tenant/default definition (school_id IS NULL) for key
 *   3. Otherwise null (definition not configured)
 *
 * Read-only: never creates, repairs, merges, or mutates configuration.
 * Never falls back to another school's definition.
 */

namespace App\Services\DynamicEnum;

use App\Models\DynamicEnum;
use App\Models\School;

class DynamicEnumResolver
{
    /**
     * Resolve the effective definition for a school + key.
     *
     * @return DynamicEnum|null null means definition_not_configured
     */
    public function resolve(School $school, string $key): ?DynamicEnum
    {
        if (trim($key) === '') {
            return null;
        }

        $schoolDefinition = DynamicEnum::query()
            ->where('school_id', $school->id)
            ->where('key', $key)
            ->first();

        if ($schoolDefinition !== null) {
            return $schoolDefinition;
        }

        return DynamicEnum::query()
            ->whereNull('school_id')
            ->where('key', $key)
            ->first();
    }

    /**
     * Resolve the effective definition and eager-load options (sorted).
     *
     * Convenience for validation / consumers that need the option set.
     */
    public function resolveWithOptions(School $school, string $key): ?DynamicEnum
    {
        $definition = $this->resolve($school, $key);

        if ($definition === null) {
            return null;
        }

        $definition->load(['options' => fn ($q) => $q->orderBy('sort_order')]);

        return $definition;
    }
}
