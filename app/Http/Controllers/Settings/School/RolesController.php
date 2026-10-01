<?php

/**
 * Permission Phase 5 — Role Management controller.
 *
 * Thin HTTP boundary: authorize via Authorization, validate input,
 * resolve authorization context, delegate to RoleManagementService.
 * Does not implement inheritance, locking, or deletion invariants.
 */

namespace App\Http\Controllers\Settings\School;

use App\Contracts\Authorization\Authorization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Roles\StoreRoleRequest;
use App\Http\Requests\Settings\Roles\SyncRolePermissionsRequest;
use App\Http\Requests\Settings\Roles\UpdateRoleRequest;
use App\Http\Requests\Settings\Roles\UpdateRoleStatusRequest;
use App\Models\Role;
use App\Services\Permission\EffectiveRole;
use App\Services\Permission\RoleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RolesController extends Controller
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly RoleManagementService $roles,
    ) {
    }

    /**
     * Effective role catalogue index (DataTable-backed).
     */
    public function index(Request $request): InertiaResponse|JsonResponse|RedirectResponse
    {
        $this->authorizeView();

        $schoolId = $this->currentSchoolId();

        try {
            if ($schoolId === null) {
                $rows = Role::query()
                    ->tenant()
                    ->withCount('permissions')
                    ->get()
                    ->map(function (Role $role) {
                        $arr = (new EffectiveRole($role, EffectiveRole::ORIGIN_TENANT))->toArray();
                        $arr['id'] = (string) $role->id;
                        $arr['permissions_count'] = (int) $role->permissions_count;

                        return $arr;
                    })
                    ->values()
                    ->all();
            } else {
                $catalogue = $this->roles->catalogueForSchool($schoolId);
                $roleIds = $catalogue->map(fn (EffectiveRole $e) => $e->roleId())->all();
                $counts = Role::query()
                    ->whereIn('id', $roleIds)
                    ->withCount('permissions')
                    ->get()
                    ->keyBy(fn (Role $r) => (string) $r->id);

                $rows = $catalogue->map(function (EffectiveRole $e) use ($counts) {
                    $row = $e->toArray();
                    $row['permissions_count'] = (int) ($counts->get($e->roleId())?->permissions_count ?? 0);
                    $row['id'] = $e->roleId();

                    return $row;
                })->values()->all();
            }

            $columns = $this->roleColumns();

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'data' => $rows,
                    'columns' => $columns,
                    'meta' => [
                        'currentPage' => 1,
                        'perPage' => count($rows),
                        'total' => count($rows),
                        'lastPage' => 1,
                    ],
                ]);
            }

            return Inertia::render('UserManagement/Roles', [
                'columns' => $columns,
                'data' => $rows,
                'meta' => [
                    'currentPage' => 1,
                    'perPage' => count($rows),
                    'total' => count($rows),
                    'lastPage' => 1,
                ],
                'capabilities' => [
                    'can_manage' => $this->canManage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('RolesController@index failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to load roles.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to load roles.');
        }
    }

    public function store(StoreRoleRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $schoolId = $this->currentSchoolId();

        try {
            $role = $this->roles->create($schoolId, $request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Role created successfully.',
                    'role' => (new EffectiveRole(
                        $role,
                        $role->isTenant() ? EffectiveRole::ORIGIN_TENANT : EffectiveRole::ORIGIN_LOCAL
                    ))->toArray() + ['id' => (string) $role->id],
                ], 201);
            }

            return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RolesController@store failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to create role.'], 500);
            }

            return redirect()->back()->withInput()->with('error', 'Failed to create role.');
        }
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $schoolId = $this->currentSchoolId();

        try {
            $effective = $this->roles->resolveEffectiveByRoleId((string) $role->getKey(), $schoolId);
            $updated = $this->roles->updateEffective($effective, $schoolId, $request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Role updated successfully.',
                    'role' => (new EffectiveRole(
                        $updated,
                        $updated->isTenant() ? EffectiveRole::ORIGIN_TENANT : EffectiveRole::ORIGIN_LOCAL
                    ))->toArray() + ['id' => (string) $updated->id],
                ]);
            }

            return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RolesController@update failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to update role.'], 500);
            }

            return redirect()->back()->withInput()->with('error', 'Failed to update role.');
        }
    }

    public function updateStatus(UpdateRoleStatusRequest $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $schoolId = $this->currentSchoolId();
        $disabled = (bool) $request->validated('disabled');

        try {
            $effective = $this->roles->resolveEffectiveByRoleId((string) $role->getKey(), $schoolId);
            $updated = $disabled
                ? $this->roles->disableEffective($effective, $schoolId)
                : $this->roles->enableEffective($effective, $schoolId);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $disabled ? 'Role disabled.' : 'Role enabled.',
                    'role' => (new EffectiveRole(
                        $updated,
                        $updated->isTenant() ? EffectiveRole::ORIGIN_TENANT : EffectiveRole::ORIGIN_LOCAL
                    ))->toArray() + ['id' => (string) $updated->id],
                ]);
            }

            return redirect()->route('admin.roles.index')->with(
                'success',
                $disabled ? 'Role disabled.' : 'Role enabled.'
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RolesController@updateStatus failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to update role status.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to update role status.');
        }
    }

    /**
     * Delete or reset depending on effective origin and context.
     */
    public function destroy(Request $request, ?Role $role = null): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $schoolId = $this->currentSchoolId();

        $ids = [];
        if ($role !== null && $role->exists) {
            $ids[] = (string) $role->getKey();
        }
        $bodyIds = $request->input('ids', $request->input('id', []));
        if (is_string($bodyIds) || is_int($bodyIds)) {
            $bodyIds = [$bodyIds];
        }
        if (is_array($bodyIds)) {
            foreach ($bodyIds as $id) {
                $ids[] = (string) $id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'ids' => 'No role selected.',
            ]);
        }

        $results = [];
        try {
            foreach ($ids as $id) {
                $effective = $this->roles->resolveEffectiveByRoleId($id, $schoolId);
                $results[] = $this->roles->deleteOrResetEffective($effective, $schoolId);
            }

            $message = count($results) === 1
                ? ($results[0]['action'] === 'reset'
                    ? 'Role reset to inherited tenant definition.'
                    : 'Role deleted.')
                : 'Roles processed.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'results' => $results]);
            }

            return redirect()->route('admin.roles.index')->with('success', $message);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RolesController@destroy failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to delete role.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete role.');
        }
    }

    /**
     * Load permission catalogue + assigned ids for an effective role.
     */
    public function permissions(Role $role): JsonResponse
    {
        $this->authorizeManage();

        $schoolId = $this->currentSchoolId();

        try {
            $effective = $this->roles->resolveEffectiveByRoleId((string) $role->getKey(), $schoolId);

            return response()->json([
                'role' => $effective->toArray() + ['id' => $effective->roleId()],
                'permission_groups' => $this->roles->permissionCatalogueGrouped(),
                'assigned_permission_ids' => $this->roles->permissionIdsFor($effective),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RolesController@permissions failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to load role permissions.'], 500);
        }
    }

    /**
     * Replace permissions for an effective role (materializes when inherited).
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse|RedirectResponse
    {
        $this->authorizeManage();

        $schoolId = $this->currentSchoolId();

        try {
            $effective = $this->roles->resolveEffectiveByRoleId((string) $role->getKey(), $schoolId);
            $updated = $this->roles->syncPermissions(
                $effective,
                $schoolId,
                $request->validated('permission_ids') ?? []
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Permissions updated.',
                    'role' => (new EffectiveRole(
                        $updated,
                        $updated->isTenant() ? EffectiveRole::ORIGIN_TENANT : EffectiveRole::ORIGIN_LOCAL
                    ))->toArray() + ['id' => (string) $updated->id],
                    'assigned_permission_ids' => $updated->permissions()->pluck('permissions.id')->all(),
                ]);
            }

            return redirect()->route('admin.roles.index')->with('success', 'Permissions updated.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RolesController@syncPermissions failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to update permissions.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to update permissions.');
        }
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        if ($user === null || $this->authorization->denies($user, 'roles.view')) {
            if ($user === null || $this->authorization->denies($user, 'roles.manage')) {
                abort(403, 'You are not authorized to view roles.');
            }
        }
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        if ($user === null || $this->authorization->denies($user, 'roles.manage')) {
            abort(403, 'You are not authorized to manage roles.');
        }
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user !== null && $this->authorization->allows($user, 'roles.manage');
    }

    private function currentSchoolId(): ?string
    {
        $school = GetSchoolModel();
        if ($school === null || $school->getKey() === null) {
            return null;
        }

        return (string) $school->getKey();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roleColumns(): array
    {
        return [
            [
                'field' => 'name',
                'header' => 'Name',
                'sortable' => true,
                'filterable' => true,
            ],
            [
                'field' => 'display_name',
                'header' => 'Display Name',
                'sortable' => true,
                'filterable' => true,
            ],
            [
                'field' => 'disabled',
                'header' => 'Status',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'field' => 'description',
                'header' => 'Description',
                'sortable' => false,
                'filterable' => true,
            ],
            [
                'field' => 'permissions_count',
                'header' => 'Permissions',
                'sortable' => true,
                'filterable' => false,
            ],
        ];
    }
}
