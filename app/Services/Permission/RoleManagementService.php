<?php

/**
 * Permission Phase 5 — role management application orchestration.
 *
 * Thin orchestration over EffectiveRoleResolver + RoleCustomizationService.
 * Controllers authorize via Authorization and delegate here; this service
 * owns create / effective update (with transparent materialization) /
 * lifecycle / permission sync, and translates domain failures into
 * ValidationException for normal field feedback.
 *
 * Does not reimplement Phase 3 locking or deletion invariants.
 */

namespace App\Services\Permission;

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleManagementService
{
    public function __construct(
        private readonly EffectiveRoleResolver $resolver,
        private readonly RoleCustomizationService $customization,
    ) {
    }

    /**
     * @return Collection<int, EffectiveRole>
     */
    public function catalogueForSchool(School|string $school): Collection
    {
        return $this->resolver->resolveForSchool($school);
    }

    /**
     * @param  array{display_name: string, name?: string|null, description?: string|null}  $data
     */
    public function create(?string $schoolId, array $data): Role
    {
        $displayName = trim((string) ($data['display_name'] ?? ''));
        if ($displayName === '') {
            throw ValidationException::withMessages([
                'display_name' => 'Display name is required.',
            ]);
        }

        $name = isset($data['name']) && is_string($data['name']) && trim($data['name']) !== ''
            ? $this->normalizeTechnicalName(trim($data['name']))
            : $this->deriveTechnicalName($displayName);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Technical name could not be derived from the display name.',
            ]);
        }

        $this->assertNameAvailableInScope($name, $schoolId);

        try {
            return Role::create([
                'name' => $name,
                'display_name' => $displayName,
                'description' => isset($data['description']) ? (string) $data['description'] : null,
                'disabled' => false,
                'school_id' => $schoolId,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw ValidationException::withMessages([
                    'name' => "A role with the technical name [{$name}] already exists in this scope.",
                ]);
            }
            throw $e;
        }
    }

    /**
     * @param  array{display_name: string, description?: string|null}  $data
     */
    public function updateEffective(
        EffectiveRole $effective,
        ?string $schoolId,
        array $data,
    ): Role {
        $displayName = trim((string) ($data['display_name'] ?? ''));
        if ($displayName === '') {
            throw ValidationException::withMessages([
                'display_name' => 'Display name is required.',
            ]);
        }

        $description = array_key_exists('description', $data)
            ? ($data['description'] !== null ? (string) $data['description'] : null)
            : $effective->role->description;

        if ($effective->isLocal()) {
            return $this->updateLocalRole($effective->role, $displayName, $description);
        }

        if ($schoolId !== null && $effective->isInherited()) {
            $school = School::query()->find($schoolId);
            if ($school === null) {
                throw ValidationException::withMessages([
                    'school' => 'Target school does not exist.',
                ]);
            }

            return DB::transaction(function () use ($effective, $school, $displayName, $description) {
                $local = $this->customization->customize($effective->role, $school);
                $local->display_name = $displayName;
                $local->description = $description;
                $local->save();

                return $local->refresh();
            });
        }

        return $this->updateLocalRole($effective->role, $displayName, $description);
    }

    public function enableEffective(EffectiveRole $effective, ?string $schoolId): Role
    {
        $role = $this->ensureMutableRole($effective, $schoolId);

        return $this->customization->enable($role);
    }

    public function disableEffective(EffectiveRole $effective, ?string $schoolId): Role
    {
        $role = $this->ensureMutableRole($effective, $schoolId);

        return $this->customization->disable($role);
    }

    /**
     * @return array{action: string, name: string}
     */
    public function deleteOrResetEffective(EffectiveRole $effective, ?string $schoolId): array
    {
        if ($schoolId !== null && $effective->isInherited()) {
            throw ValidationException::withMessages([
                'role' => 'This role belongs to your tenant and cannot be deleted from this school. You can disable it for this school or contact your tenant administrator to remove the tenant role.',
            ]);
        }

        $isReset = false;
        if ($effective->isLocal() && $schoolId !== null) {
            $tenantExists = Role::query()
                ->tenant()
                ->where('name', $effective->name())
                ->exists();
            $isReset = $tenantExists;
        }

        $this->customization->delete($effective->role);

        return [
            'action' => $isReset ? 'reset' : 'deleted',
            'name' => $effective->name(),
        ];
    }

    /** @return list<int|string> */
    public function permissionIdsFor(EffectiveRole $effective): array
    {
        return $effective->role->permissions()->pluck('permissions.id')->all();
    }

    /**
     * @param  list<int|string>  $permissionIds
     */
    public function syncPermissions(EffectiveRole $effective, ?string $schoolId, array $permissionIds): Role
    {
        $ids = $this->validatePermissionIds($permissionIds);
        $role = $this->ensureMutableRole($effective, $schoolId);

        return DB::transaction(function () use ($role, $ids) {
            $locked = $this->customization->lockRole((string) $role->getKey());
            $locked->permissions()->sync($ids);

            return $locked->refresh();
        });
    }

    public function resolveEffectiveByRoleId(string $roleId, ?string $schoolId): EffectiveRole
    {
        $role = Role::query()->whereKey($roleId)->first();
        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => 'Role does not exist.',
            ]);
        }

        if ($schoolId === null) {
            if (! $role->isTenant()) {
                throw ValidationException::withMessages([
                    'role' => 'Role is not available in the current authorization context.',
                ]);
            }

            return new EffectiveRole($role, EffectiveRole::ORIGIN_TENANT);
        }

        $effective = $this->resolver->resolveForSchool($schoolId)
            ->first(fn (EffectiveRole $e) => $e->roleId() === (string) $role->getKey());

        if ($effective === null) {
            throw ValidationException::withMessages([
                'role' => 'Role is not available in the current authorization context.',
            ]);
        }

        return $effective;
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<array{id: mixed, name: string, display_name: string|null, description: string|null}>}>
     */
    public function permissionCatalogueGrouped(): array
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'description']);

        $groups = [];
        foreach ($permissions as $permission) {
            $name = (string) $permission->name;
            $key = str_contains($name, '.') ? explode('.', $name, 2)[0] : 'general';
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => Str::headline(str_replace(['_', '-'], ' ', $key)),
                    'permissions' => [],
                ];
            }
            $groups[$key]['permissions'][] = [
                'id' => $permission->id,
                'name' => $name,
                'display_name' => $permission->display_name,
                'description' => $permission->description,
            ];
        }

        return array_values($groups);
    }

    private function updateLocalRole(Role $role, string $displayName, ?string $description): Role
    {
        return DB::transaction(function () use ($role, $displayName, $description) {
            $locked = $this->customization->lockRole((string) $role->getKey());
            $locked->display_name = $displayName;
            $locked->description = $description;
            $locked->save();

            return $locked->refresh();
        });
    }

    private function ensureMutableRole(EffectiveRole $effective, ?string $schoolId): Role
    {
        if ($effective->isLocal()) {
            return $effective->role;
        }

        if ($schoolId === null) {
            return $effective->role;
        }

        $school = School::query()->find($schoolId);
        if ($school === null) {
            throw ValidationException::withMessages([
                'school' => 'Target school does not exist.',
            ]);
        }

        $existingLocal = Role::query()
            ->forSchool($schoolId)
            ->where('name', $effective->name())
            ->first();

        if ($existingLocal !== null) {
            return $existingLocal;
        }

        return $this->customization->customize($effective->role, $school);
    }

    private function assertNameAvailableInScope(string $name, ?string $schoolId): void
    {
        $query = Role::query()->where('name', $name);
        if ($schoolId === null) {
            $query->whereNull('school_id');
        } else {
            $query->where('school_id', $schoolId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => "A role with the technical name [{$name}] already exists in this scope.",
            ]);
        }
    }

    private function deriveTechnicalName(string $displayName): string
    {
        $slug = Str::slug($displayName, '_');

        return $this->normalizeTechnicalName($slug);
    }

    private function normalizeTechnicalName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_\-]+/', '_', $name) ?? $name;
        $name = preg_replace('/_+/', '_', $name) ?? $name;

        return trim($name, '_-');
    }

    /**
     * @param  list<int|string>  $permissionIds
     * @return list<int|string>
     */
    private function validatePermissionIds(array $permissionIds): array
    {
        $ids = array_values(array_unique(array_filter($permissionIds, fn ($id) => $id !== null && $id !== '')));
        if ($ids === []) {
            return [];
        }

        $existing = Permission::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $missing = array_diff(array_map('strval', $ids), $existing);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'permission_ids' => 'One or more permission IDs are invalid or unknown.',
            ]);
        }

        return $ids;
    }

    private function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $code === '23505' || $driverCode === 1062 || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
