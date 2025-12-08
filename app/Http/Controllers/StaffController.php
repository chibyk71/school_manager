<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Employee\DepartmentRole;
use App\Models\Employee\Staff;
use App\Models\SchoolSection;
use App\Services\UserService;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * StaffController – Fully aligned with the new polymorphic User + Profile architecture
 */
class StaffController extends BaseSchoolController
{
    public function __construct(protected UserService $userService) {}

    /**
     * Display a listing of staff members.
     */
    public function index(Request $request)
    {
        try {
            $school = $this->getActiveSchool();

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

            $query = Staff::where('school_id', $school->id)
                ->with([
                    'user:id,name,email',
                    'schoolSections:id,name',
                    'departmentRole:id,name',
                    'customFields',
                ])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

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
            return $this->respondWithError($request, 'Unable to load staff list.');
        }
    }

    /**
     * Show form for creating a new staff member.
     */
    public function create(Request $request)
    {
        try {
            $school = $this->getActiveSchool();

            return Inertia::render('HRM/Staffs/Create', [
                'customFields' => $this->getCustomFieldsForForm($school->id, Staff::class),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'departmentRoles' => DepartmentRole::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load staff creation form: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load creation form.');
        }
    }

    /**
     * Store a new staff member using UserService.
     */
    public function store(StoreStaffRequest $request)
    {
        try {
            $school = $this->getActiveSchool();
            $data = $request->validated();

            // Enrich data for UserService
            $data['profile_type'] = 'staff';
            $data['profilable'] = [
                'school_id' => $school->id,
                'department_role_id' => $data['department_role_id'] ?? null,
                'staff_id_number' => $data['staff_id_number'] ?? null,
                'date_of_employment' => $data['date_of_employment'] ?? now(),
            ];

            // Create user + profile + staff record
            $user = $this->userService->create($data);

            $staff = $user->staff; // Thanks to HasProfile trait

            DB::transaction(function () use ($data, $staff) {
                // Custom fields
                if (!empty($data['custom_fields'])) {
                    $staff->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync school sections
                if (!empty($data['section_ids'])) {
                    $staff->syncSections($data['section_ids']);
                }
            });

            return $this->respondWithSuccess(
                $request,
                'Staff member created successfully.',
                'staff.index'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create staff: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to create staff member: ' . $e->getMessage());
        }
    }

    /**
     * Show a single staff member (API only).
     */
    public function show(Request $request, Staff $staff)
    {
        try {
            Gate::authorize('view', $staff);

            $staff->load([
                'user:id,name,email',
                'schoolSections:id,name',
                'departmentRole:id,name',
                'customFields',
            ]);

            return response()->json(['staff' => $staff]);
        } catch (\Exception $e) {
            Log::error('Failed to load staff ID ' . $staff->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Unable to load staff member'], 500);
        }
    }

    /**
     * Edit form for staff member.
     */
    public function edit(Request $request, Staff $staff)
    {
        try {
            Gate::authorize('update', $staff);
            $school = $this->getActiveSchool();

            $staff->load([
                'user:id,name,email',
                'schoolSections:id,name',
                'departmentRole:id,name',
            ]);

            return Inertia::render('HRM/Staffs/Edit', [
                'staff' => $staff,
                'customFields' => $this->getCustomFieldsForForm($school->id, Staff::class),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'departmentRoles' => DepartmentRole::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load staff edit form: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load edit form.');
        }
    }

    /**
     * Update staff member using UserService.
     */
    public function update(UpdateStaffRequest $request, Staff $staff)
    {
        try {
            Gate::authorize('update', $staff);
            $data = $request->validated();
            $user = $staff->user;

            // Update core user + profile via UserService
            $this->userService->update($user, $data);

            DB::transaction(function () use ($data, $staff) {
                // Update staff-specific fields
                $staff->update([
                    'department_role_id' => $data['department_role_id'] ?? $staff->department_role_id,
                    'staff_id_number' => $data['staff_id_number'] ?? $staff->staff_id_number,
                    'date_of_employment' => $data['date_of_employment'] ?? $staff->date_of_employment,
                ]);

                // Custom fields
                if (!empty($data['custom_fields'])) {
                    $staff->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync sections
                if (isset($data['section_ids'])) {
                    $staff->syncSections($data['section_ids']);
                }
            });

            return $this->respondWithSuccess(
                $request,
                'Staff member updated successfully.',
                'staff.index'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update staff ID ' . $staff->id . ': ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to update staff member.');
        }
    }

    /**
     * Bulk delete staff members.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Staff::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:staff,id',
            ]);

            $force = $request->boolean('force');
            $ids = $request->input('ids');

            $deleted = $force
                ? Staff::whereIn('id', $ids)->forceDelete()
                : Staff::whereIn('id', $ids)->delete();

            $message = $deleted ? "Deleted {$deleted} staff member(s)" : 'No staff were deleted';

            return $this->respondWithSuccess($request, $message, 'staff.index');
        } catch (\Exception $e) {
            Log::error('Failed to delete staff: ' . $e->getMessage());
            return $this->respondWithError($request, 'Failed to delete staff members.');
        }
    }

    /**
     * Restore soft-deleted staff.
     */
    public function restore(Request $request, string $id)
    {
        $staff = Staff::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $staff);

        try {
            $staff->restore();
            $staff->user->notify(new \App\Notifications\StaffUpdatedNotification($staff));

            return response()->json(['message' => 'Staff restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore staff ID ' . $id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore staff'], 500);
        }
    }
}
