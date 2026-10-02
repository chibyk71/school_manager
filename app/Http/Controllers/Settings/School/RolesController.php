<?php

/**
 * Permission Phase 5 — Role Management controller.
 *
 * Thin HTTP boundary: authorize via Authorization, validate input,
 * resolve authorization context, delegate to RoleManagementService.
 * Does not implement inheritance, locking, or deletion invariants.
 *
 * Index uses the established DataTable query/response contract against the
 * effective-role catalogue (search/filter/sort/pagination applied in memory
 * after EffectiveRoleResolver — frontend never computes inheritance).
 */

namespace App\Http\Controllers\Settings\School;

use App\Contracts\Authorization\Authorization;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataTable\DataTableBulkActionRequest;
use App\Http\Requests\Settings\Roles\StoreRoleRequest;
use App\Http\Requests\Settings\Roles\SyncRolePermissionsRequest;
use App\Http\Requests\Settings\Roles\UpdateRoleRequest;
use App\Http\Requests\Settings\Roles\UpdateRoleStatusRequest;
use App\Models\Role;
use App\Services\Permission\EffectiveRole;
use App\Services\Permission\RoleManagementService;
use App\Support\DataTable\BulkActionCapability;
use App\Support\DataTable\DataTableQuery;
use App\Support\DataTable\DataTableQueryNormalizer;
use App\Support\DataTable\DataTableSelection;
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

    public function index(Request $request): InertiaResponse|JsonResponse|RedirectResponse
    {
        $this->authorizeView();

        $schoolId = $this->currentSchoolId();

        try {
            $rows = $this->buildEffectiveRows($schoolId);
            $columns = $this->roleColumns();
            $payload = $this->paginateEffectiveCatalogue($request, $rows, $columns);

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json($payload);
            }

            return Inertia::render('UserManagement/Roles', [
                'columns' => $payload['columns'],
                'data' => $payload['data'],
                'meta' => $payload['meta'],
                'capabilities' => array_merge(
                    $payload['capabilities'],
                    ['can_manage' => $this->canManage()],
                ),
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
     * Bulk enable/disable via the established DataTable bulk-action contract.
     *
     * Body: { selection: { type: 'ids'|'query', ... }, action: 'enable'|'disable' }
     */
    public function bulkUpdateStatus(DataTableBulkActionRequest $request): JsonResponse
    {
        $this->authorizeManage();

        $schoolId = $this->currentSchoolId();
        $action = $request->action();

        if (! in_array($action, ['enable', 'disable'], true)) {
            throw ValidationException::withMessages([
                'action' => 'Unsupported bulk action. Allowed: enable, disable.',
            ]);
        }

        $disabled = $action === 'disable';

        try {
            $ids = $this->resolveBulkSelectionIds($request->selection(), $schoolId);
            $outcome = $this->roles->bulkSetStatus($schoolId, $ids, $disabled);

            $verb = $disabled ? 'disabled' : 'enabled';
            $message = $outcome['failed'] === 0
                ? "{$outcome['processed']} role(s) {$verb}."
                : "{$outcome['processed']} role(s) {$verb}; {$outcome['failed']} failed.";

            return response()->json([
                'message' => $message,
                'processed' => $outcome['processed'] + $outcome['failed'],
                'succeeded' => $outcome['processed'],
                'failed' => $outcome['failed'],
                'skipped' => 0,
                'results' => $outcome['results'],
            ], $outcome['failed'] > 0 && $outcome['processed'] === 0 ? 422 : 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('RolesController@bulkUpdateStatus failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to update role status.'], 500);
        }
    }

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

    /** @return list<array<string, mixed>> */
    private function buildEffectiveRows(?string $schoolId): array
    {
        if ($schoolId === null) {
            return Role::query()
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
        }

        $catalogue = $this->roles->catalogueForSchool($schoolId);
        $roleIds = $catalogue->map(fn (EffectiveRole $e) => $e->roleId())->all();
        $counts = Role::query()
            ->whereIn('id', $roleIds)
            ->withCount('permissions')
            ->get()
            ->keyBy(fn (Role $r) => (string) $r->id);

        return $catalogue->map(function (EffectiveRole $e) use ($counts) {
            $row = $e->toArray();
            $row['permissions_count'] = (int) ($counts->get($e->roleId())?->permissions_count ?? 0);
            $row['id'] = $e->roleId();

            return $row;
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $columns
     * @return array{data: list<array<string, mixed>>, columns: list<array<string, mixed>>, meta: array{currentPage: int, perPage: int, total: int, lastPage: int}, capabilities: array{bulkActions: list<array<string, mixed>>, exportable: bool, maxSelectionIds: int}}
     */
    private function paginateEffectiveCatalogue(Request $request, array $rows, array $columns): array
    {
        $dtQuery = DataTableQueryNormalizer::fromConfig()->fromRequest($request);
        $filtered = $this->filterEffectiveRows($rows, $dtQuery);
        $sorted = $this->sortEffectiveRows($filtered, $dtQuery);

        $total = count($sorted);
        $perPage = $dtQuery->perPage;
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($dtQuery->page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($sorted, $offset, $perPage);

        return [
            'data' => array_values($pageRows),
            'columns' => $columns,
            'meta' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
            'capabilities' => [
                'bulkActions' => $this->bulkActionCapabilities(),
                'exportable' => false,
                'maxSelectionIds' => (int) config('tables.selection.max_ids', 500),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterEffectiveRows(array $rows, DataTableQuery $dtQuery): array
    {
        $search = $dtQuery->search !== null ? mb_strtolower($dtQuery->search) : null;
        $searchFields = ['name', 'display_name', 'description'];

        return array_values(array_filter($rows, function (array $row) use ($search, $searchFields, $dtQuery) {
            if ($search !== null) {
                $hay = '';
                foreach ($searchFields as $field) {
                    $hay .= ' '.mb_strtolower((string) ($row[$field] ?? ''));
                }
                if (! str_contains($hay, $search)) {
                    return false;
                }
            }

            foreach ($dtQuery->filters as $filter) {
                $field = $filter['field'];
                $operator = $filter['operator'];
                $value = $filter['value'];
                $cell = $row[$field] ?? null;

                if (! $this->matchFilter($cell, $operator, $value)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function matchFilter(mixed $cell, string $operator, mixed $value): bool
    {
        return match ($operator) {
            'equals', 'eq' => $this->looseEquals($cell, $value),
            'notEquals', 'neq' => ! $this->looseEquals($cell, $value),
            'contains' => str_contains(mb_strtolower((string) $cell), mb_strtolower((string) $value)),
            'startsWith' => str_starts_with(mb_strtolower((string) $cell), mb_strtolower((string) $value)),
            'endsWith' => str_ends_with(mb_strtolower((string) $cell), mb_strtolower((string) $value)),
            'gt' => is_numeric($cell) && is_numeric($value) && (float) $cell > (float) $value,
            'gte' => is_numeric($cell) && is_numeric($value) && (float) $cell >= (float) $value,
            'lt' => is_numeric($cell) && is_numeric($value) && (float) $cell < (float) $value,
            'lte' => is_numeric($cell) && is_numeric($value) && (float) $cell <= (float) $value,
            'in' => is_array($value) && in_array($cell, $value, false),
            'boolean', 'is' => (bool) $cell === (bool) $value,
            default => true,
        };
    }

    private function looseEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortEffectiveRows(array $rows, DataTableQuery $dtQuery): array
    {
        if ($dtQuery->sorts === []) {
            return $rows;
        }

        usort($rows, function (array $a, array $b) use ($dtQuery) {
            foreach ($dtQuery->sorts as $sort) {
                $field = $sort['field'];
                $dir = $sort['direction'] === 'desc' ? -1 : 1;
                $av = $a[$field] ?? null;
                $bv = $b[$field] ?? null;

                if (is_numeric($av) && is_numeric($bv)) {
                    $cmp = $av <=> $bv;
                } elseif (is_bool($av) || is_bool($bv)) {
                    $cmp = ((int) (bool) $av) <=> ((int) (bool) $bv);
                } else {
                    $cmp = strcasecmp((string) $av, (string) $bv);
                }

                if ($cmp !== 0) {
                    return $cmp * $dir;
                }
            }

            return 0;
        });

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function bulkActionCapabilities(): array
    {
        if (! $this->canManage()) {
            return [];
        }

        return [
            (new BulkActionCapability(
                id: 'enable',
                label: 'Enable Selected',
                icon: 'pi pi-check-circle',
                requiresConfirmation: true,
                semantics: 'partial',
            ))->toArray(),
            (new BulkActionCapability(
                id: 'disable',
                label: 'Disable Selected',
                icon: 'pi pi-ban',
                requiresConfirmation: true,
                semantics: 'partial',
            ))->toArray(),
        ];
    }

    /** @return list<string> */
    private function resolveBulkSelectionIds(DataTableSelection $selection, ?string $schoolId): array
    {
        if ($selection->isIds()) {
            return array_values(array_unique(array_map('strval', $selection->ids)));
        }

        $rows = $this->buildEffectiveRows($schoolId);
        $membership = $selection->query;
        $queryInput = [
            'page' => 1,
            'perPage' => max(count($rows), 1),
            'search' => $membership?->search,
            'filters' => $membership?->filters ?? [],
        ];
        $dtQuery = DataTableQueryNormalizer::fromConfig()->fromArray($queryInput);
        $filtered = $this->filterEffectiveRows($rows, $dtQuery);

        return array_values(array_filter(array_map(
            fn (array $row) => (string) ($row['id'] ?? ''),
            $filtered
        )));
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

    /** @return list<array<string, mixed>> */
    private function roleColumns(): array
    {
        return [
            [
                'field' => 'name',
                'header' => 'Name',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
            ],
            [
                'field' => 'display_name',
                'header' => 'Display Name',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
            ],
            [
                'field' => 'disabled',
                'header' => 'Status',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
                'searchable' => false,
            ],
            [
                'field' => 'description',
                'header' => 'Description',
                'sortable' => false,
                'filterable' => true,
                'searchable' => true,
            ],
            [
                'field' => 'permissions_count',
                'header' => 'Permissions',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
            ],
        ];
    }
}
