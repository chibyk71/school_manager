<?php

/**
 * Permission Phase 3 — resolved effective role for a school context.
 *
 * Not an Eloquent model and not persisted. Callers distinguish inherited
 * (tenant/global) vs local definitions without competing shadowing logic.
 */

namespace App\Services\Permission;

use App\Models\Role;

final class EffectiveRole
{
    public const ORIGIN_TENANT = 'tenant';

    public const ORIGIN_LOCAL = 'local';

    public function __construct(
        public readonly Role $role,
        public readonly string $origin,
    ) {
        if (! in_array($origin, [self::ORIGIN_TENANT, self::ORIGIN_LOCAL], true)) {
            throw new \InvalidArgumentException(
                "Invalid effective role origin [{$origin}]. Expected tenant or local."
            );
        }
    }

    public function isInherited(): bool
    {
        return $this->origin === self::ORIGIN_TENANT;
    }

    public function isLocal(): bool
    {
        return $this->origin === self::ORIGIN_LOCAL;
    }

    public function isDisabled(): bool
    {
        return $this->role->isDisabled();
    }

    public function isEnabled(): bool
    {
        return $this->role->isEnabled();
    }

    public function name(): string
    {
        return (string) $this->role->name;
    }

    public function roleId(): string
    {
        return (string) $this->role->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role_id' => $this->roleId(),
            'name' => $this->name(),
            'display_name' => $this->role->display_name,
            'description' => $this->role->description,
            'disabled' => $this->isDisabled(),
            'origin' => $this->origin,
            'is_inherited' => $this->isInherited(),
            'is_local' => $this->isLocal(),
            'school_id' => $this->role->school_id,
            'presentation_name' => $this->role->presentationName(),
        ];
    }
}
