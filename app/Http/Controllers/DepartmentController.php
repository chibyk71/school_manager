<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Employee\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Department resources.
 */
class DepartmentController extends Controller
{
    /**
     * Display a listing of departments with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class); // Policy-based authorization

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'role_names',
                    'relation' => 'roles',
                    'relatedField' => 'display_name',
                    'filterable' => true,
                    'sortable' => false,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Department::with(['roles:id,display_name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $departments = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($departments);
            }

            return Inertia::render('HRM/Departments', [
                'departments' => $departments,
                'roles' => Role::select('id', 'display_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch departments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch departments'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch departments']);
        }
    }

    /**
     * Store a newly created department in storage.
     *
     * @param StoreDepartmentRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreDepartmentRequest $request)
    {
        Gate::authorize('create', Department::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            $department = Department::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Department created successfully'], 201)
                : redirect()->route('departments.index')->with(['success' => 'Department created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create department: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create department'], 500)
                : redirect()->back()->with(['error' => 'Failed to create department'])->withInput();
        }
    }

    /**
     * Display the specified department.
     *
     * @param Request $request
     * @param Department $department
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Department $department)
    {
        Gate::authorize('view', $department); // Policy-based authorization

        try {
            $department->load(['roles:id,display_name']);
            return response()->json(['department' => $department]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch department: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch department'], 500);
        }
    }

    /**
     * Update the specified department in storage.
     *
     * @param UpdateDepartmentRequest $request
     * @param Department $department
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        Gate::authorize('update', $department); // Policy-based authorization

        try {
            $validated = $request->validated();
            $department->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Department updated successfully'])
                : redirect()->route('departments.index')->with(['success' => 'Department updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update department: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update department'], 500)
                : redirect()->back()->with(['error' => 'Failed to update department'])->withInput();
        }
    }

    /**
     * Remove the specified department(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Department::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:departments,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? Department::whereIn('id', $ids)->forceDelete()
                : Department::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Department(s) deleted successfully' : 'No departments were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('departments.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete departments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete department(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete department(s)']);
        }
    }

    /**
     * Restore a soft-deleted department.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $department = Department::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $department); // Policy-based authorization

        try {
            $department->restore();
            return response()->json(['message' => 'Department restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore department: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore department'], 500);
        }
    }

    /**
     * Assign roles to a department.
     *
     * @param Request $request
     * @param Department $department
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function assignRole(Request $request, Department $department)
    {
        Gate::authorize('assignRole', $department); // Policy-based authorization

        try {
            $request->validate([
                'roles' => 'required|array',
                'roles.*' => 'exists:roles,id',
            ]);

            $department->roles()->sync($request->input('roles'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Roles assigned successfully'])
                : redirect()->back()->with(['success' => 'Roles assigned successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to assign roles to department: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to assign roles'], 500)
                : redirect()->back()->with(['error' => 'Failed to assign roles']);
        }
    }

    /**
     * Get users in a department.
     *
     * @param Request $request
     * @param Department $department
     * @return \Illuminate\Http\JsonResponse
     */
    public function users(Request $request, Department $department)
    {
        Gate::authorize('viewUsers', $department); // Policy-based authorization

        try {
            $users = $department->users()->with(['roles:id,display_name'])->get();
            return response()->json(['users' => $users]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch department users: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch department users'], 500);
        }
    }

    /**
     * Get roles in a department.
     *
     * @param Request $request
     * @param Department $department
     * @return \Illuminate\Http\JsonResponse
     */
    public function roles(Request $request, Department $department)
    {
        Gate::authorize('view', $department); // Policy-based authorization

        try {
            $roles = $department->roles()->select('id', 'name', 'display_name')->get();
            return response()->json(['data' => $roles]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch department roles: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch department roles'], 500);
        }
    }
}