<?php

/**
 * Effective option after tenant baseline + school sparse overlay merge (Phase 3R).
 *
 * Provenance and enforcement metadata are derived at resolution time and are
 * never persisted as configuration state.
 */

namespace App\Services\DynamicEnum;

final class ResolvedDynamicEnumOption
{
    public const SOURCE_TENANT = 'tenant';

    public const SOURCE_SCHOOL = 'school';

    public const ENFORCEMENT_TENANT_REQUIRED = 'tenant_required';

    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly int $sortOrder,
        public readonly bool $isActive,
        public readonly bool $isRequired,
        public readonly ?string $color,
        public readonly ?string $icon,
        public readonly string $source,
        public readonly bool $overridden,
        public readonly bool $enforced,
        public readonly ?string $enforcementReason,
    ) {}

    public function isTenantSource(): bool
    {
        return $this->source === self::SOURCE_TENANT;
    }

    public function isSchoolSource(): bool
    {
        return $this->source === self::SOURCE_SCHOOL;
    }

    public function isSelectable(): bool
    {
        return $this->isActive;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'is_required' => $this->isRequired,
            'color' => $this->color,
            'icon' => $this->icon,
            'source' => $this->source,
            'overridden' => $this->overridden,
            'enforced' => $this->enforced,
            'enforcement_reason' => $this->enforcementReason,
        ];
    }
}
