<?php

/**
 * Temporary Phase 1 stub for the legacy HasDynamicEnum trait.
 *
 * The normalized Dynamic Enum foundation (Phase 1) no longer stores options as JSON
 * and no longer exposes visibleToSchool / forModel / name / applies_to.
 *
 * Models may continue to `use HasDynamicEnum` and declare getDynamicEnumProperties()
 * without runtime errors. Runtime option resolution, assignment validation, and
 * admin-facing option lists are deferred to Phases 3–5.
 *
 * This trait must not call the removed Dynamic Enum API and must not reintroduce
 * SchoolScope fallback, JSON options, or applies_to identity.
 */

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasDynamicEnum
{
    /**
     * @return array<int, string>
     */
    abstract public function getDynamicEnumProperties(): array;

    /**
     * Phase 1 stub — definition lifecycle is Phase 2 / admin is Phase 4.
     *
     * @param  array<int, array{value: string, label: string, color?: string|null}>  $options
     */
    public function addDynamicEnum(string $name, string $label, array $options = []): never
    {
        $this->rejectLegacyDynamicEnumCall(__FUNCTION__);
    }

    /**
     * Phase 1 stub — no effective resolution until Phase 3.
     *
     * @return array<int, array{value: string, label: string, color?: string|null}>
     */
    public function getDynamicEnumOptions(string $property): array
    {
        return [];
    }

    /**
     * Phase 1 stub — returns empty map so forms do not call removed model scopes.
     *
     * @return array<string, array<int, array{value: string, label: string, color?: string|null}>>
     */
    public function getVisibleEnums(): array
    {
        $result = [];
        foreach ($this->getDynamicEnumProperties() as $property) {
            $result[$property] = [];
        }

        return $result;
    }

    /**
     * Phase 1: assignment is not validated against Dynamic Enum options.
     * Phase 3 will reintroduce selected-value validation.
     */
    public function setAttribute($key, $value)
    {
        return parent::setAttribute($key, $value);
    }

    private function rejectLegacyDynamicEnumCall(string $method): never
    {
        Log::warning("HasDynamicEnum::{$method} called during Phase 1; Dynamic Enum lifecycle is deferred.");

        throw new \RuntimeException(
            "HasDynamicEnum::{$method} is unavailable until Dynamic Enum Phase 2–4. ".
            'Phase 1 only establishes the normalized schema foundation.'
        );
    }
}
