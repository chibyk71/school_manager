<?php

/**
 * Dynamic Enum Phase 4 — administration application boundary.
 *
 * Orchestrates administrative use cases. Domain invariants remain in
 * DynamicEnumLifecycleService and DynamicEnumResolver.
 *
 * School scope is never taken from request IDs for mutation; callers must
 * pass an authorized, resolved School instance (or null for tenant ops).
 *
 * Option mutations always receive the route definition key so the option's
 * dynamic_enum_id is verified against that definition (cross-definition
 * UUID attack surface closed).
 */

namespace App\Services\DynamicEnum;

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DynamicEnumAdministrationService
{
    public function __construct(
        private readonly DynamicEnumLifecycleService $lifecycle,
        private readonly DynamicEnumResolver $resolver,
    ) {
    }

    /**
     * Catalogue of application-owned definitions (school_id IS NULL).
     *
     * @return Collection<int, DynamicEnum>
     */
    public function catalogue(): Collection
    {
        return DynamicEnum::query()
            ->whereNull('school_id')
            ->orderBy('key')
            ->get();
    }

    /**
     * Effective configuration for tenant default (no school overlay).
     */
    public function effectiveTenant(string $key): ResolvedDynamicEnum
    {
        return $this->resolver->resolve($key);
    }

    /**
     * Effective configuration for an explicit school.
     */
    public function effectiveForSchool(School $school, string $key): ResolvedDynamicEnum
    {
        return $this->resolver->resolveForSchool($school, $key);
    }

    /**
     * Administrative detail payload: definition + effective options + capability hints.
     *
     * @return array<string, mixed>
     */
    public function detail(string $key, ?School $school, bool $canManage, bool $canManageGlobals): array
    {
        $definition = DynamicEnum::query()
            ->whereNull('school_id')
            ->where('key', $key)
            ->first();

        if ($definition === null) {
            throw new DynamicEnumNotConfiguredException($key);
        }

        $resolved = $school !== null
            ? $this->resolver->resolveForSchool($school, $key)
            : $this->resolver->resolve($key);

        $tenantOptions = DynamicEnumOption::query()
            ->where('dynamic_enum_id', $definition->id)
            ->whereNull('school_id')
            ->get()
            ->keyBy(fn (DynamicEnumOption $o) => DynamicEnumValue::canonicalize((string) $o->value));

        $schoolOptions = $school === null
            ? collect()
            : DynamicEnumOption::query()
                ->where('dynamic_enum_id', $definition->id)
                ->where('school_id', $school->id)
                ->get()
                ->keyBy(fn (DynamicEnumOption $o) => DynamicEnumValue::canonicalize((string) $o->value));

        $options = $resolved->options->map(function (ResolvedDynamicEnumOption $opt) use (
            $tenantOptions,
            $schoolOptions,
            $canManage,
            $canManageGlobals,
            $school
        ) {
            $tenantRow = $tenantOptions->get($opt->value);
            $schoolRow = $schoolOptions->get($opt->value);

            return array_merge($opt->toArray(), [
                'tenant_label' => $tenantRow?->label,
                'school_label' => $schoolRow?->label,
                'tenant_option_id' => $tenantRow?->id,
                'school_option_id' => $schoolRow?->id,
                'capabilities' => $this->optionCapabilities(
                    $opt,
                    $tenantRow,
                    $schoolRow,
                    $canManage,
                    $canManageGlobals,
                    $school !== null
                ),
            ]);
        })->values()->all();

        return [
            'key' => $resolved->key,
            'label' => $resolved->label,
            'description' => $resolved->description,
            'definition_id' => $resolved->definitionId,
            'scope' => $school !== null ? 'school' : 'tenant',
            'school_id' => $school?->id,
            'options' => $options,
            'capabilities' => [
                'can_edit_definition' => $canManageGlobals || ($canManage && $school !== null),
                'can_manage_tenant_options' => $canManageGlobals,
                'can_manage_school_options' => $canManage && $school !== null,
                'can_make_required' => $canManageGlobals,
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function optionCapabilities(
        ResolvedDynamicEnumOption $opt,
        ?DynamicEnumOption $tenantRow,
        ?DynamicEnumOption $schoolRow,
        bool $canManage,
        bool $canManageGlobals,
        bool $schoolContext
    ): array {
        $isSchoolOwned = $schoolRow !== null;
        $isTenantOwned = $tenantRow !== null;
        $isOverride = $isSchoolOwned && $isTenantOwned;
        $isSchoolOnly = $isSchoolOwned && ! $isTenantOwned;

        return [
            'can_edit' => ($canManageGlobals && $isTenantOwned && ! $schoolContext)
                || ($canManage && $schoolContext && $isSchoolOwned),
            'can_activate' => ($canManageGlobals && $isTenantOwned && ! $schoolContext)
                || ($canManage && $schoolContext && $isSchoolOwned),
            'can_deactivate' => ($canManageGlobals && $isTenantOwned && ! $schoolContext)
                || ($canManage && $schoolContext && $isSchoolOwned),
            'can_delete' => ($canManageGlobals && $isTenantOwned && ! $schoolContext)
                || ($canManage && $schoolContext && $isSchoolOnly),
            'can_override' => $canManage && $schoolContext && $isTenantOwned && ! $isSchoolOwned,
            'can_reset' => $canManage && $schoolContext && $isOverride,
            'can_make_required' => $canManageGlobals && $isTenantOwned && ! $schoolContext,
            'can_remove_required' => $canManageGlobals && $isTenantOwned && ! $schoolContext && $opt->isRequired,
        ];
    }

    public function updateTenantDefinitionPresentation(string $key, array $attributes): DynamicEnum
    {
        $definition = $this->requireTenantDefinition($key);

        return $this->lifecycle->updateDefinitionPresentation($definition, $attributes);
    }

    public function updateSchoolDefinitionPresentation(School $school, string $key, array $attributes): DynamicEnum
    {
        $this->requireTenantDefinition($key);

        $existing = DynamicEnum::query()
            ->where('school_id', $school->id)
            ->where('key', $key)
            ->first();

        if ($existing === null) {
            $label = $attributes['label'] ?? $key;
            $existing = DynamicEnum::create([
                'school_id' => $school->id,
                'key' => $key,
                'label' => $label,
                'description' => $attributes['description'] ?? null,
            ]);
        } else {
            $payload = array_intersect_key($attributes, array_flip(['label', 'description']));
            $existing->fill($payload);
            $existing->save();
        }

        return $existing->refresh();
    }

    public function createTenantOption(string $key, string $value, string $label, array $attributes = []): DynamicEnumOption
    {
        $definition = $this->requireTenantDefinition($key);

        return $this->lifecycle->createTenantOption($definition, $value, $label, $attributes);
    }

    public function updateTenantOption(string $key, DynamicEnumOption $option, array $attributes): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->assertTenantOption($option);

        return $this->lifecycle->updateOptionPresentation($option, $attributes);
    }

    public function activateTenantOption(string $key, DynamicEnumOption $option): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->assertTenantOption($option);

        return $this->lifecycle->activateOption($option);
    }

    public function deactivateTenantOption(string $key, DynamicEnumOption $option): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->assertTenantOption($option);

        return $this->lifecycle->deactivateOption($option);
    }

    public function makeTenantOptionRequired(string $key, DynamicEnumOption $option): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->assertTenantOption($option);

        return $this->lifecycle->makeOptionRequired($option);
    }

    public function removeTenantOptionRequired(string $key, DynamicEnumOption $option): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->assertTenantOption($option);

        return $this->lifecycle->makeOptionOptional($option);
    }

    public function deleteTenantOption(string $key, DynamicEnumOption $option): void
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->assertTenantOption($option);
        $this->lifecycle->permanentlyDeleteTenantOption($option);
    }

    public function createSchoolOption(School $school, string $key, string $value, string $label, array $attributes = []): DynamicEnumOption
    {
        $definition = $this->requireTenantDefinition($key);
        unset($attributes['is_required']);

        return $this->lifecycle->createSchoolOption($definition, $school, $value, $label, $attributes);
    }

    public function createSchoolOverride(School $school, string $key, string $value, string $label, array $attributes = []): DynamicEnumOption
    {
        $definition = $this->requireTenantDefinition($key);
        unset($attributes['is_required']);

        return $this->lifecycle->createSchoolOverride($definition, $school, $value, $label, $attributes);
    }

    public function updateSchoolOption(School $school, string $key, DynamicEnumOption $option, array $attributes): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->lifecycle->assertSchoolOwnsOption($option, $school);
        unset($attributes['is_required']);

        return $this->lifecycle->updateOptionPresentation($option, $attributes);
    }

    public function activateSchoolOption(School $school, string $key, DynamicEnumOption $option): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->lifecycle->assertSchoolOwnsOption($option, $school);

        return $this->lifecycle->activateOption($option);
    }

    public function deactivateSchoolOption(School $school, string $key, DynamicEnumOption $option): DynamicEnumOption
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->lifecycle->assertSchoolOwnsOption($option, $school);

        return $this->lifecycle->deactivateOption($option);
    }

    public function removeSchoolOverride(School $school, string $key, DynamicEnumOption $option): void
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->lifecycle->assertSchoolOwnsOption($option, $school);
        $this->lifecycle->removeSchoolOverride($option);
    }

    public function deleteSchoolOption(School $school, string $key, DynamicEnumOption $option): void
    {
        $this->assertOptionBelongsToKey($option, $key);
        $this->lifecycle->assertSchoolOwnsOption($option, $school);
        $this->lifecycle->permanentlyDeleteSchoolOption($option);
    }

    /**
     * Ensure the option row belongs to the application definition identified by $key.
     * Prevents cross-definition mutation via a valid option UUID from another enum.
     */
    public function assertOptionBelongsToKey(DynamicEnumOption $option, string $key): void
    {
        $definition = $this->requireTenantDefinition($key);

        if ((string) $option->dynamic_enum_id !== (string) $definition->id) {
            throw ValidationException::withMessages([
                'option' => 'Option does not belong to the specified Dynamic Enum definition.',
            ]);
        }
    }

    private function requireTenantDefinition(string $key): DynamicEnum
    {
        $definition = DynamicEnum::query()
            ->whereNull('school_id')
            ->where('key', $key)
            ->first();

        if ($definition === null) {
            throw new DynamicEnumNotConfiguredException($key);
        }

        return $definition;
    }

    private function assertTenantOption(DynamicEnumOption $option): void
    {
        if (! $option->isTenantOption()) {
            throw ValidationException::withMessages([
                'option' => 'Expected a tenant baseline option.',
            ]);
        }
    }
}
