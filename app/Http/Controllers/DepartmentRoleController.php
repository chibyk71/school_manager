<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRoleRequest;
use App\Http\Requests\UpdateDepartmentRoleRequest;
use App\Models\Employee\Department;
use App\Models\Employee\DepartmentRole;
use App\Models\SchoolSection;
use App\Notifications\DepartmentRoleUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing DepartmentRole resources.
 *
 * Handles CRUD operations for department roles, scoped to the active school.
 */
class DepartmentRoleController extends Controller
{
    /**
     * Display a listing of department roles with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', DepartmentRole::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $extraFields = [
                [
                    'field' => 'department_name',
                    'relation' => 'department',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'role_name',
                    'relation' => 'role',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'school_section_name',
                    'relation' => 'schoolSection',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            $query = DepartmentRole::where('school_id', $school->id)
                ->with([
                    'department:id,name',
                    'role:id,name',
                    'schoolSection:id,name',
                    'staff.user:id,name',
                ])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $departmentRoles = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($departmentRoles);
            }

            return Inertia::render('HRM/DepartmentRoles', [
                'departmentRoles' => $departmentRoles,
                'departments' => Department::where('school_id', $school->id)->select('id', 'name')->get(),
                'roles' => \App\Models\Role::select('id', 'name')->get(),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch department roles: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch department roles'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch department roles']);
        }
    }

    /**
     * Show the form for creating a new department role.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', DepartmentRole::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('HRM/DepartmentRoles/Create', [
                'departments' => Department::where('school_id', $school->id)->select('id', 'name')->get(),
                'roles' => \App\Models\Role::select('id', 'name')->get(),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    /**
     * Store a newly created department role in storage.
     *
     * @param StoreDepartmentRoleRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreDepartmentRoleRequest $request)
    {
        Gate::authorize('create', DepartmentRole::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $departmentRole = DB::transaction(function () use ($request, $school) {
                $validated = $request->validated();
                $validated['school_id'] = $school->id;
                $validated['name'] = $validated['name'] ?? "{$validated['department_id']}-{$validated['role_id']}" . ($validated['school_section_id'] ? "-{$validated['school_section_id']}" : '');

                $departmentRole = DepartmentRole::create($validated);

                // Notify affected staff
                $staff = $departmentRole->staff()->with('user')->get();
                foreach ($staff as $s) {
                    $s->user->notify(new DepartmentRoleUpdatedNotification($departmentRole));
                }

                return $departmentRole;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Department role created successfully'], 201)
                : redirect()->route('department-roles.index')->with(['success' => 'Department role created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create department role: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create department role'], 500)
                : redirect()->back()->with(['error' => 'Failed to create department role'])->withInput();
        }
    }

    /**
     * Display the specified department role.
     *
     * @param Request $request
     * @param DepartmentRole $departmentRole
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, DepartmentRole $departmentRole)
    {
        Gate::authorize('view', $departmentRole);

        try {
            $departmentRole->load([
                'department:id,name',
                'role:id,name',
                'schoolSection:id,name',
                'staff.user:id,name',
            ]);
            return response()->json(['department_role' => $departmentRole]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch department role ID ' . $departmentRole->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch department role'], 500);
        }
    }

    /**
     * Show the form for editing the specified department role.
     *
     * @param DepartmentRole $departmentRole
     * @return \Inertia\Response
     */
    public function edit(DepartmentRole $departmentRole)
    {
        Gate::authorize('update', $departmentRole);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $departmentRole->load([
                'department:id,name',
                'role:id,name',
                'schoolSection:id,name',
            ]);

            return Inertia::render('HRM/DepartmentRoles/Edit', [
                'departmentRole' => $departmentRole,
                'departments' => Department::where('school_id', $school->id)->select('id', 'name')->get(),
                'roles' => \App\Models\Role::select('id', 'name')->get(),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form for ID ' . $departmentRole->id . ': ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    /**
     * Update the specified department role in storage.
     *
     * @param UpdateDepartmentRoleRequest $request
     * @param DepartmentRole $departmentRole
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateDepartmentRoleRequest $request, DepartmentRole $departmentRole)
    {
        Gate::authorize('update', $departmentRole);

        try {
            $departmentRole = DB::transaction(function () use ($request, $departmentRole) {
                $validated = $request->validated();
                $validated['name'] = $validated['name'] ?? "{$validated['department_id']}-{$validated['role_id']}" . ($validated['school_section_id'] ? "-{$validated['school_section_id']}" : '');

                $departmentRole->update($validated);

                // Notify affected staff
                $staff = $departmentRole->staff()->with('user')->get();
                foreach ($staff as $s) {
                    $s->user->notify(new DepartmentRoleUpdatedNotification($departmentRole));
                }

                return $departmentRole;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Department role updated successfully'])
                : redirect()->route('department-roles.index')->with(['success' => 'Department role updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update department role ID ' . $departmentRole->id . ': ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update department role'], 500)
                : redirect()->back()->with(['error' => 'Failed to update department role'])->withInput();
        }
    }

    /**
     * Remove the specified department role(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', DepartmentRole::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:department_role,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? DepartmentRole::whereIn('id', $ids)->forceDelete()
                : DepartmentRole::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Department role(s) deleted successfully' : 'No department roles were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('department-roles.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete department roles: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete department role(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete department role(s)']);
        }
    }

    /**
     * Restore a soft-deleted department role.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $departmentRole = DepartmentRole::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $departmentRole);

        try {
            $departmentRole->restore();

            // Notify affected staff
            $staff = $departmentRole->staff()->with('user')->get();
            foreach ($staff as $s) {
                $s->user->notify(new DepartmentRoleUpdatedNotification($departmentRole));
            }

            return response()->json(['message' => 'Department role restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore department role ID ' . $id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore department role'], 500);
        }
    }
}