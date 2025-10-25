<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Employee\DepartmentRole;
use App\Models\Employee\Staff;
use App\Models\SchoolSection;
use App\Notifications\StaffUpdatedNotification;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing staff members in the school management system.
 *
 * Handles CRUD operations for staff, including custom fields and school section relationships.
 * Scoped to the active school for multi-tenancy.
 *
 * @package App\Http\Controllers
 */
class StaffController extends Controller
{
    /**
     * Display a listing of staff members with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Staff::class); // Policy-based authorization

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'user_name',
                    'relation' => 'user',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'user_email',
                    'relation' => 'user',
                    'relatedField' => 'email',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'department_role_name',
                    'relation' => 'departmentRole',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Staff::where('school_id', $school->id)
                ->with([
                    'user:id,name,email',
                    'schoolSections:id,name',
                    'departmentRole:id,name',
                    'customFields',
                ])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $staff = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($staff);
            }

            return Inertia::render('HRM/Staffs', [
                'staff' => $staff,
                'columns' => ColumnDefinitionHelper::fromModel(new Staff()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch staff: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Unable to load staff'], 500)
                : redirect()->route('dashboard')->with(['error' => 'Unable to load staff']);
        }
    }

    /**
     * Show the form for creating a new staff member.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', Staff::class); // Policy-based authorization

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('HRM/Staffs/Create', [
                'customFields' => Staff::getCustomFieldsForForm($school->id, Staff::class),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'departmentRoles' => DepartmentRole::where('school_id', $school->id)->select('id', 'name')->get(),
                'users' => \App\Models\User::where('school_id', $school->id)->select('id', 'name', 'email')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load staff creation form: ' . $e->getMessage());
            return redirect()->route('staff.index')->with(['error' => 'Unable to load creation form']);
        }
    }

    /**
     * Store a newly created staff member in storage.
     *
     * @param StoreStaffRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreStaffRequest $request)
    {
        Gate::authorize('create', Staff::class); // Policy-based authorization

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $staff = DB::transaction(function () use ($request, $school) {
                $data = $request->validated();
                $data['school_id'] = $school->id;

                // Create staff
                $staff = Staff::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $data['user_id'],
                    'school_id' => $school->id,
                    'department_role_id' => $data['department_role_id'] ?? null,
                ]);

                // Save custom fields
                if (!empty($data['custom_fields'])) {
                    $staff->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync school sections
                if (!empty($data['section_ids'])) {
                    $staff->syncSections($data['section_ids']);
                }

                // Notify the user
                $staff->user->notify(new StaffUpdatedNotification($staff));

                return $staff;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Staff created successfully'], 201)
                : redirect()->route('staff.index')->with(['success' => 'Staff created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create staff: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Unable to create staff'], 500)
                : redirect()->back()->with(['error' => 'Unable to create staff'])->withInput();
        }
    }

    /**
     * Display the specified staff member.
     *
     * @param Request $request
     * @param Staff $staff
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Staff $staff)
    {
        Gate::authorize('view', $staff); // Policy-based authorization

        try {
            $staff->load([
                'user:id,name,email',
                'schoolSections:id,name',
                'departmentRole:id,name',
                'customFields',
            ]);
            return response()->json(['staff' => $staff]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch staff ID ' . $staff->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Unable to load staff'], 500);
        }
    }

    /**
     * Show the form for editing the specified staff member.
     *
     * @param Staff $staff
     * @return \Inertia\Response
     */
    public function edit(Staff $staff)
    {
        Gate::authorize('update', $staff); // Policy-based authorization

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $staff->load([
                'user:id,name,email',
                'schoolSections:id,name',
                'departmentRole:id,name',
                'customFields',
            ]);

            return Inertia::render('HRM/Staffs/Edit', [
                'staff' => $staff,
                'customFields' => Staff::getCustomFieldsForForm($school->id, Staff::class),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'departmentRoles' => DepartmentRole::where('school_id', $school->id)->select('id', 'name')->get(),
                'users' => \App\Models\User::where('school_id', $school->id)->select('id', 'name', 'email')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load staff edit form for ID ' . $staff->id . ': ' . $e->getMessage());
            return redirect()->route('staff.index')->with(['error' => 'Unable to load edit form']);
        }
    }

    /**
     * Update the specified staff member in storage.
     *
     * @param UpdateStaffRequest $request
     * @param Staff $staff
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateStaffRequest $request, Staff $staff)
    {
        Gate::authorize('update', $staff); // Policy-based authorization

        try {
            $staff = DB::transaction(function () use ($request, $staff) {
                $data = $request->validated();

                // Update staff
                $staff->update([
                    'user_id' => $data['user_id'] ?? $staff->user_id,
                    'department_role_id' => $data['department_role_id'] ?? $staff->department_role_id,
                ]);

                // Save custom fields
                if (!empty($data['custom_fields'])) {
                    $staff->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync school sections
                if (isset($data['section_ids'])) {
                    $staff->syncSections($data['section_ids']);
                }

                // Notify the user
                $staff->user->notify(new StaffUpdatedNotification($staff));

                if (isset($data['department_role_id']) && $data['department_role_id'] !== $staff->department_role_id) {
                    $departmentRole = DepartmentRole::find($data['department_role_id']);
                    if ($departmentRole) {
                        $staff->user->notify(new \App\Notifications\DepartmentRoleUpdatedNotification($departmentRole));
                    }
                }

                return $staff;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Staff updated successfully'])
                : redirect()->route('staff.index')->with(['success' => 'Staff updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update staff ID ' . $staff->id . ': ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Unable to update staff'], 500)
                : redirect()->back()->with(['error' => 'Unable to update staff'])->withInput();
        }
    }

    /**
     * Remove the specified staff member(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Staff::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:staff,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? Staff::whereIn('id', $ids)->forceDelete()
                : Staff::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Staff deleted successfully' : 'No staff were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('staff.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete staff: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete staff'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete staff']);
        }
    }

    /**
     * Restore a soft-deleted staff member.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $staff = Staff::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $staff); // Policy-based authorization

        try {
            $staff->restore();
            $staff->user->notify(new StaffUpdatedNotification($staff));
            return response()->json(['message' => 'Staff restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore staff ID ' . $id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore staff'], 500);
        }
    }
}