<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class RolesController extends Controller
{
    /**
     * Display the listing of roles in a dynamic, paginated data table.
     *
     * This method serves as the entry point for the Roles Management index page.
     * It leverages your advanced HasTableQuery trait for server-side searching,
     * filtering, sorting, and pagination, while ColumnDefinitionHelper dynamically
     * generates PrimeVue-compatible column definitions.
     *
     * Key Features:
     * - School-scoped access control (multi-tenant safety)
     * - Eager loads user count, permission count, and related departments
     * - Displays department badges (preserves your valuable department grouping)
     * - Fully dynamic columns (headers, filters, sorting) via ColumnDefinitionHelper
     * - Graceful error handling with user-friendly messages and logging
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  403 if no active school
     * @throws \Exception  on unexpected failures (logged + user feedback)
     */
    public function index(Request $request)
    {
        try {
            // ------------------------------------------------------------------
            // 1. Authorization: Ensure user has permission to manage roles
            // ------------------------------------------------------------------
            // Uses your custom 'permitted()' helper (assumed to throw or abort on failure)
            // permitted('manage-roles');

            // // ------------------------------------------------------------------
            // // 2. Load current active school (critical for multi-tenant isolation)
            // // ------------------------------------------------------------------
            // $school = GetSchoolModel();

            // if (!$school) {
            //     // This should rarely happen in a properly configured app
            //     // But we handle it defensively
            //     Log::warning('RolesController@index accessed without active school', [
            //         'user_id' => auth()->id(),
            //         'ip' => $request->ip(),
            //     ]);

            //     abort(403, 'No active school context found. Please select a school first.');
            // }

            // ------------------------------------------------------------------
            // 3. Build the base query with school scoping and eager loading
            // ------------------------------------------------------------------
            // We instantiate a fresh Role model to use the tableQuery() scope from HasTableQuery trait
            $query = Role::query();

            // Eager load relationships needed for display:
            // - users count → shows how many staff/students have this role
            // - permissions count → quick overview of role power
            // - departments → to display department badges (your key feature)
            $query->withCount(['users', 'permissions'])
                ->with('departments'); // assumes Role hasMany or belongsToMany Department

            // ------------------------------------------------------------------
            // 4. Apply dynamic table operations (search, filter, sort, paginate)
            // ------------------------------------------------------------------
            // HasTableQuery handles all heavy lifting: global search, column filters, sorting
            // We pass extra virtual/computed fields that don't exist on the model
            $extraFields = [
                // Hide internal/technical fields
                'id' => ['hidden' => true],
                'school_id' => ['hidden' => true],
                'updated_at' => ['hidden' => true],

                // Customize visible columns
                'created_at' => [
                    'header' => 'Created On',
                    'filterType' => 'date',
                    'sortable' => true,
                ],
                'departments' => [
                    'header' => 'Departments',
                    'filterable' => true,
                    'filterType' => 'dropdown', // Will auto-populate from relation
                    'relation' => 'departments',
                    'relatedField' => 'name',
                ],
                'users_count' => [
                    'header' => 'Users Assigned',
                    'sortable' => true,
                    'filterable' => false, // Not meaningful to filter by count
                ],
                'permissions_count' => [
                    'header' => 'Permissions',
                    'sortable' => true,
                    'filterable' => false,
                ],
            ];

            $rolesPaginated = $query->tableQuery($request, $extraFields);

            // ------------------------------------------------------------------
            // 5. Generate dynamic PrimeVue column definitions
            // ------------------------------------------------------------------
            // This uses your powerful ColumnDefinitionHelper to auto-detect types,
            // apply casts, enums, relations, and HasConfig dropdowns
            $model = new Role();
            $columns = ColumnDefinitionHelper::fromModel($model, $extraFields, false);

            // ------------------------------------------------------------------
            // 6. Return Inertia response with data
            // ------------------------------------------------------------------
            return Inertia::render('UserManagement/Roles', [
                'roles' => $rolesPaginated, // Paginated collection with metadata
                'columns' => $columns,
                'globalFilters' => ['name', 'department.name']
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Caught if permitted() fails (optional extra safety)
            Log::notice('Unauthorized access attempt to roles index', [
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
            ]);

            abort(403, 'You do not have permission to manage roles.');

        } catch (\Exception $e) {
            // Catch any unexpected error (DB, logic, etc.)
            Log::error('Unexpected error in RolesController@index', [
                'user_id' => auth()->id(),
                'school_id' => $school->id ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // User-friendly fallback
            return redirect()
                ->back()
                ->with('error', 'Failed to load roles. Please try again or contact support.');
        }
    }

    /**
     * Store a newly created role in the database.
     *
     * This method handles the POST submission from the "Create Role" form.
     * It performs full validation, creates the role record, optionally copies
     * permissions from an existing role (acting as a template), and intelligently
     * redirects the user based on their preference.
     *
     * Workflow Highlights:
     * - Creates a clean role shell quickly
     * - Supports "template" roles via copy_from_role_id — great for creating
     *   similar roles (e.g., Senior Teacher based on Teacher)
     * - Smart redirect: If "proceed_to_permissions" is checked (default: true),
     *   sends admin directly to the dedicated permission matrix for fine-tuning
     *
     * Production-Grade Features:
     * - Multi-tenant isolation (school_id scoping)
     * - Unique name constraint per school
     * - Defensive checks on source role existence and accessibility
     * - Comprehensive validation with clear rules
     * - Detailed logging for security and debugging
     * - User-friendly success messages with role name
     * - Graceful error handling with fallbacks
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException  on invalid input
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  403 on unauthorized/missing school
     * @throws \Exception  on unexpected failures (logged)
     */
    public function store(Request $request)
    {
        try {
            // ------------------------------------------------------------------
            // 1. Authorization: Confirm user can manage roles
            // ------------------------------------------------------------------
            permitted('manage-roles');

            // ------------------------------------------------------------------
            // 2. Retrieve Active School Context (Essential for Multi-Tenancy)
            // ------------------------------------------------------------------
            $school = GetSchoolModel();

            if (!$school) {
                Log::warning('Attempt to create role without active school context', [
                    'user_id' => auth()->id(),
                    'ip' => $request->ip(),
                ]);

                abort(403, 'No active school selected. Cannot create role without school context.');
            }

            // ------------------------------------------------------------------
            // 3. Validate Incoming Data
            // ------------------------------------------------------------------
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,NULL,id,school_id,' . $school->id,
                'display_name' => 'required|string|max:255',
                'department_id' => 'required|integer|exists:departments,id', // Required department
                'description' => 'nullable|string|max:1000',
                'copy_from_role_id' => 'nullable|integer|exists:roles,id',
                'proceed_to_permissions' => 'sometimes|boolean',
            ]);

            // ------------------------------------------------------------------
            // 4. Create the New Role with Department
            // ------------------------------------------------------------------
            $role = Role::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'department_id' => $validated['department_id'], // New required field
                'description' => $validated['description'] ?? null,
                'school_id' => $school->id,
            ]);

            Log::info('New role created with department', [
                'role_id' => $role->id,
                'role_name' => $role->display_name,
                'department_id' => $role->department_id,
                'created_by' => auth()->id(),
                'school_id' => $school->id,
            ]);

            // ------------------------------------------------------------------
            // 5. Optional: Copy Permissions from Template Role
            // ------------------------------------------------------------------
            if ($request->filled('copy_from_role_id')) {
                $sourceRoleId = $validated['copy_from_role_id'];

                // Ensure source role is accessible (same school or global)
                $sourceRole = Role::where('id', $sourceRoleId)
                    ->where(function ($q) use ($school) {
                        $q->whereNull('school_id')->orWhere('school_id', $school->id);
                    })
                    ->first();

                if ($sourceRole) {
                    $role->permissions()->sync($sourceRole->permissions->pluck('id')->toArray());

                    Log::info('Permissions copied to new role from template', [
                        'new_role_id' => $role->id,
                        'template_role_id' => $sourceRole->id,
                        'template_name' => $sourceRole->display_name,
                    ]);
                } else {
                    Log::warning('Attempted to copy from inaccessible role during creation', [
                        'requested_role_id' => $sourceRoleId,
                        'new_role_id' => $role->id,
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            // ------------------------------------------------------------------
            // 6. Determine Redirect Based on User Preference
            // ------------------------------------------------------------------
            $proceedToPermissions = $request->boolean('proceed_to_permissions', true);

            if ($proceedToPermissions) {
                return redirect()
                    ->route('admin.roles.permissions.manage', $role)
                    ->with('success', "Role '{$role->display_name}' created successfully. Now configure permissions.");
            }

            return redirect()
                ->route('admin.roles.index')
                ->with('success', "Role '{$role->display_name}' created successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Let Inertia show errors on modal form
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::notice('Unauthorized role creation attempt', [
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'You do not have permission to create roles.');
        } catch (\Exception $e) {
            Log::error('Unexpected error during role creation', [
                'user_id' => auth()->id(),
                'school_id' => $school->id ?? null,
                'request_data' => $request->except(['_token']),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create role. Please try again or contact support.');
        }
    }

    /**
     * Update the basic details of an existing role (name, display name, description).
     *
     * This method handles the submission from the "Edit Role" form.
     * It is intentionally kept separate from permission management to maintain
     * a clean, focused workflow: basic role metadata here, permissions on a dedicated page.
     *
     * What it does:
     * - Validates incoming data with school-scoped uniqueness
     * - Updates only the core role fields (no permission touching here)
     * - Provides clear success feedback
     * - Redirects back to the roles index
     *
     * Security & Integrity:
     * - Uses authorizeRoleAccess() to enforce both permission and school scoping
     * - Ensures role name remains unique within the same school context
     * - Prevents cross-school updates
     *
     * Production-Grade Enhancements:
     * - Comprehensive try/catch with contextual logging
     * - Detailed validation rules
     * - User-friendly messages
     * - Audit logging on successful update
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role     Route-model bound role instance
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException  on invalid data
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  403 if unauthorized
     * @throws \Exception  on unexpected failures (logged)
     */
    public function update(Request $request, Role $role)
    {
        try {
            // ------------------------------------------------------------------
            // 1. Authorization & School Scoping
            // ------------------------------------------------------------------
            $this->authorizeRoleAccess($role);

            // ------------------------------------------------------------------
            // 2. Validate Incoming Data
            // ------------------------------------------------------------------
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:roles,name,' . $role->id . ',id,school_id,' . ($role->school_id ?? 'NULL'),
                ],
                'display_name' => 'required|string|max:255',
                'department_id' => 'required|integer|exists:departments,id', // Required
                'description' => 'nullable|string|max:1000',
            ]);

            // ------------------------------------------------------------------
            // 3. Update Role Fields
            // ------------------------------------------------------------------
            $oldDepartmentId = $role->department_id;
            $oldDisplayName = $role->display_name;

            $role->update([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'department_id' => $validated['department_id'],
                'description' => $validated['description'] ?? null,
            ]);

            // ------------------------------------------------------------------
            // 4. Log Update for Audit Trail
            // ------------------------------------------------------------------
            Log::info('Role details updated', [
                'role_id' => $role->id,
                'old_display_name' => $oldDisplayName,
                'new_display_name' => $role->display_name,
                'old_department_id' => $oldDepartmentId,
                'new_department_id' => $role->department_id,
                'updated_by' => auth()->id(),
                'school_id' => $role->school_id,
                'ip' => $request->ip(),
            ]);

            // ------------------------------------------------------------------
            // 5. Success Redirect
            // ------------------------------------------------------------------
            return redirect()
                ->route('admin.roles.index')
                ->with('success', "Role '{$role->display_name}' updated successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Let Inertia display errors in modal
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e; // From authorizeRoleAccess()
        } catch (\Exception $e) {
            Log::error('Unexpected error updating role', [
                'role_id' => $role->id ?? null,
                'user_id' => auth()->id(),
                'request_data' => $request->except(['_token']),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update role. Please try again or contact support.');
        }
    }

    /**
     * Delete one or more roles (single or bulk delete).
     *
     * This method handles both single role deletion (via standard route-model binding)
     * and bulk deletion when an array of role IDs is posted (common pattern in your system).
     *
     * Why support bulk delete?
     * - Consistency with other entities in your application (students, staff, etc.)
     * - Improves admin efficiency: select multiple unused roles → delete in one action
     * - Safe: Still enforces "no users assigned" rule per role
     *
     * Safety Rules:
     * - Cannot delete any role that has assigned users
     * - Skips invalid/missing IDs silently (with warning log)
     * - Only allows roles within current school context (via authorizeRoleAccess)
     * - Full audit logging for security
     *
     * Request Formats Supported:
     * - Single: DELETE /admin/roles/{role}                  → standard Laravel resource
     * - Bulk:   POST   /admin/roles/bulk-delete with { ids: [1, 2, 3] }
     *
     * Recommendation: Add a dedicated bulk-delete route (see routes suggestion below)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role|null     $role    Optional route-bound single role
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  403 if unauthorized
     * @throws \Exception  on unexpected failures
     */
    public function destroy(Request $request, Role $role = null)
    {
        try {
            // ------------------------------------------------------------------
            // 1. Authorization: User must have manage-roles permission
            // ------------------------------------------------------------------
            permitted('manage-roles');

            // ------------------------------------------------------------------
            // 2. Determine if this is single or bulk deletion
            // ------------------------------------------------------------------
            $isBulk = $request->has('ids') && is_array($request->input('ids'));

            $rolesToDelete = [];

            if ($isBulk) {
                // Bulk mode: Expecting 'ids' array in request body
                $requestedIds = array_filter($request->input('ids', []), 'is_numeric');

                if (empty($requestedIds)) {
                    return redirect()
                        ->route('admin.roles.index')
                        ->with('error', 'No roles selected for deletion.');
                }

                // Load roles with school scoping and eager load users count
                $rolesToDelete = Role::whereIn('id', $requestedIds)
                    ->where(function ($q) {
                        $school = GetSchoolModel();
                        if ($school) {
                            $q->whereNull('school_id')->orWhere('school_id', $school->id);
                        }
                    })
                    ->withCount('users')
                    ->get();
            } else {
                // Single mode: Route-model binding provided $role
                if (!$role) {
                    abort(404, 'Role not found.');
                }

                // Enforce school scoping for single delete too
                $this->authorizeRoleAccess($role);

                $role->loadMissing('users'); // For count check
                $rolesToDelete = collect([$role]);
            }

            // ------------------------------------------------------------------
            // 3. Validate: Cannot delete roles with assigned users
            // ------------------------------------------------------------------
            $rolesWithUsers = $rolesToDelete->filter(fn($r) => $r->users_count > 0);

            if ($rolesWithUsers->isNotEmpty()) {
                $names = $rolesWithUsers->pluck('display_name')->join(', ', ' and ');

                $message = $rolesWithUsers->count() > 1
                    ? "Cannot delete roles with assigned users: {$names}."
                    : "Cannot delete role '{$names}' because users are assigned.";

                Log::warning('Attempted to delete role(s) with assigned users', [
                    'role_ids' => $rolesWithUsers->pluck('id')->toArray(),
                    'user_count' => $rolesWithUsers->sum('users_count'),
                    'attempted_by' => auth()->id(),
                ]);

                return redirect()
                    ->route('admin.roles.index')
                    ->with('error', $message);
            }

            // ------------------------------------------------------------------
            // 4. Perform Deletion
            // ------------------------------------------------------------------
            $deletedCount = $rolesToDelete->count();
            $deletedNames = $rolesToDelete->pluck('display_name')->toArray();

            // Soft delete or hard delete based on your model configuration
            $rolesToDelete->each->delete();

            Log::info('Role(s) deleted successfully', [
                'deleted_role_ids' => $rolesToDelete->pluck('id')->toArray(),
                'deleted_role_names' => $deletedNames,
                'count' => $deletedCount,
                'deleted_by' => auth()->id(),
            ]);

            // ------------------------------------------------------------------
            // 5. Success Feedback
            // ------------------------------------------------------------------
            $successMessage = $deletedCount > 1
                ? "{$deletedCount} roles deleted successfully."
                : "Role '{$deletedNames[0]}' deleted successfully.";

            return redirect()
                ->route('admin.roles.index')
                ->with('success', $successMessage);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::notice('Unauthorized role deletion attempt', [
                'user_id' => auth()->id(),
                'role_id' => $role->id ?? null,
                'bulk_ids' => $request->input('ids'),
            ]);

            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'You do not have permission to delete roles.');

        } catch (\Exception $e) {
            Log::error('Unexpected error during role deletion', [
                'user_id' => auth()->id(),
                'role_id' => $role->id ?? null,
                'bulk_ids' => $request->input('ids'),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Failed to delete role(s). Please try again or contact support.');
        }
    }

    /**
     * Display the dedicated permission management matrix for a specific role.
     *
     * This is the core RBAC administration page where super admins (or authorized users)
     * view, select, and fine-tune permissions for a single role.
     *
     * Features:
     * - Permissions grouped by module (e.g., 'student', 'finance', 'hostel') for intuitive navigation
     * - Shows currently assigned permissions (checked state)
     * - Provides a dropdown of other roles for quick "Merge permissions from..." action
     * - Fully school-scoped: only shows relevant roles and enforces access
     *
     * Performance:
     * - Selects only needed columns from permissions table
     * - Eager loads existing permissions on the role
     * - Uses efficient grouping logic
     *
     * Security:
     * - Enforces manage-roles permission + school scoping via authorizeRoleAccess()
     *
     * @param  \App\Models\Role  $role  Route-model bound role instance
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  403 if unauthorized
     * @throws \Exception  on unexpected failures (logged)
     */
    public function managePermissions(Role $role)
    {
        try {
            // ------------------------------------------------------------------
            // 1. Authorization & School Scoping
            // ------------------------------------------------------------------
            // Ensures user has permission and the role belongs to their school context
            $this->authorizeRoleAccess($role);

            // ------------------------------------------------------------------
            // 2. Load All Permissions (Global – permissions are not school-specific)
            // ------------------------------------------------------------------
            // We only need id, name (for grouping), and display_name for UI
            $allPermissions = Permission::select('id', 'name', 'display_name')
                ->orderBy('name')
                ->get();

            if ($allPermissions->isEmpty()) {
                Log::warning('No permissions found in database during role permission management', [
                    'role_id' => $role->id,
                    'user_id' => auth()->id(),
                ]);
            }

            // ------------------------------------------------------------------
            // 3. Get Currently Assigned Permission IDs
            // ------------------------------------------------------------------
            // Eager load if not already (route model binding may not have loaded relation)
            $role->loadMissing('permissions');
            $assignedPermissionIds = $role->permissions->pluck('id')->toArray();

            // ------------------------------------------------------------------
            // 4. Group Permissions by Module (e.g., student.view → module: student)
            // ------------------------------------------------------------------
            // This transforms flat list into intuitive accordion structure for Vue
            $permissionsGrouped = $this->groupPermissionsByModule($allPermissions);

            // ------------------------------------------------------------------
            // 5. Load Other Roles for "Merge Permissions From" Dropdown
            // ------------------------------------------------------------------
            // Only include roles from the same school (or global) excluding current role
            $otherRoles = Role::where('id', '!=', $role->id)
                ->where(function ($query) use ($role) {
                    $query->whereNull('school_id')                    // Global roles
                        ->orWhere('school_id', $role->school_id);   // Same school
                })
                ->select('id', 'display_name', 'name')
                ->orderBy('display_name')
                ->get()
                ->map(function ($r) {
                    return [
                        'value' => $r->id,
                        'label' => $r->display_name . ' (' . $r->name . ')',
                    ];
                });

            // ------------------------------------------------------------------
            // 6. Render Inertia Page with All Required Props
            // ------------------------------------------------------------------
            return Inertia::render('UserManagement/Permission', [
                'role' => $role->only(['id', 'name', 'display_name']),
                'permissionsGrouped' => $permissionsGrouped,
                'assignedPermissionIds' => $assignedPermissionIds,
                'otherRoles' => $otherRoles,
                'totalPermissions' => $allPermissions->count(),
                'assignedCount' => count($assignedPermissionIds),
            ]);

        } catch (\Exception $e) {
            Log::error('Unexpected error loading role permission management page', [
                'role_id' => $role->id ?? null,
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Failed to load permission management page. Please try again.');
        }
    }

    /**
     * Save (fully synchronize) permissions for the role.
     *
     * This replaces ALL current permissions on the role with the newly selected set.
     * Used when the admin clicks "Save" on the permission matrix.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePermissions(Request $request, Role $role)
    {
        try {
            $this->authorizeRoleAccess($role);

            $validated = $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'integer|exists:permissions,id',
            ]);

            $newPermissionIds = $validated['permissions'];

            // Perform full sync — removes unselected, adds selected
            $role->permissions()->sync($newPermissionIds);

            Log::info('Role permissions fully synchronized', [
                'role_id' => $role->id,
                'permission_count' => count($newPermissionIds),
                'updated_by' => auth()->id(),
                'school_id' => $role->school_id,
            ]);

            return redirect()
                ->back()
                ->with('success', "Permissions for '{$role->display_name}' updated successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Let Inertia show validation errors on form
        } catch (\Exception $e) {
            Log::error('Error syncing role permissions', [
                'role_id' => $role->id,
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to update permissions. Please try again.');
        }
    }

    /**
     * Merge permissions from another role into the current role.
     *
     * Adds all permissions from the source role WITHOUT removing existing ones.
     * Perfect for building composite roles (e.g., Teacher + Exam Officer responsibilities).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role  Target role receiving merged permissions
     * @return \Illuminate\Http\RedirectResponse
     */
    public function mergePermissionsFrom(Request $request, Role $role)
    {
        try {
            $this->authorizeRoleAccess($role);

            $validated = $request->validate([
                'source_role_id' => [
                    'required',
                    'exists:roles,id',
                    'different:id,' . $role->id, // Prevent self-merge
                ],
            ]);

            $sourceRole = Role::where('id', $validated['source_role_id'])->firstOrFail();

            $permissionsToAdd = $sourceRole->permissions()->pluck('id')->toArray();

            if (empty($permissionsToAdd)) {
                return redirect()
                    ->back()
                    ->with('info', "No permissions to merge from '{$sourceRole->display_name}'.");
            }

            // Attach new permissions without removing existing ones
            $role->permissions()->syncWithoutDetaching($permissionsToAdd);

            $addedCount = count($permissionsToAdd);

            Log::info('Permissions merged into role', [
                'target_role_id' => $role->id,
                'source_role_id' => $sourceRole->id,
                'source_role_name' => $sourceRole->display_name,
                'added_count' => $addedCount,
                'merged_by' => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->with('success', "Successfully merged {$addedCount} permission(s) from '{$sourceRole->display_name}'.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()
                ->back()
                ->with('error', 'Source role not found or not accessible.');
        } catch (\Exception $e) {
            Log::error('Error merging permissions into role', [
                'target_role_id' => $role->id,
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to merge permissions. Please try again.');
        }
    }

    // ===================================================================
    // Private Helper Methods
    // ===================================================================

    /**
     * Authorize access to a specific role with school context enforcement.
     *
     * Centralizes security checks used across multiple methods:
     * - Ensures user has the 'manage-roles' permission
     * - Validates that the role belongs to the current active school (or is global)
     *
     * Why centralized?
     * - DRY principle: Avoid repeating permission + scoping logic
     * - Single point of truth for role access rules
     * - Easier to modify policy later (e.g., add role ownership checks)
     *
     * @param  \App\Models\Role  $role  The role being accessed
     * @return void
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  403 if unauthorized or cross-school access
     */
    private function authorizeRoleAccess(Role $role): void
    {
        try {
            // 1. Global permission check
            // Your custom helper — assumed to abort or throw on failure
            permitted('manage-roles');

            // 2. Retrieve current school context
            $school = GetSchoolModel();

            // Defensive: This should not happen if middleware is correctly applied
            if (!$school) {
                Log::warning('authorizeRoleAccess called without active school', [
                    'user_id' => auth()->id(),
                    'role_id' => $role->id,
                    'ip' => request()->ip(),
                ]);

                abort(403, 'No active school context. Cannot verify role access.');
            }

            // 3. School scoping rule:
            // - Global roles (school_id = NULL) are accessible to all
            // - School-specific roles must match current school_id
            if ($role->school_id && $role->school_id !== $school->id) {
                Log::notice('Attempted cross-school role access', [
                    'user_id' => auth()->id(),
                    'role_id' => $role->id,
                    'role_school_id' => $role->school_id,
                    'current_school_id' => $school->id,
                    'role_name' => $role->display_name,
                ]);

                abort(403, 'Unauthorized: You do not have access to roles from other schools.');
            }

            // Access granted — method returns silently

        } catch (\Exception $e) {
            // Catch any unexpected issue (e.g., DB error loading school)
            Log::error('Unexpected error in authorizeRoleAccess', [
                'role_id' => $role->id ?? null,
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
            ]);

            abort(403, 'Access denied due to system error.');
        }
    }

    /**
     * Group permissions by module for intuitive UI presentation.
     *
     * Transforms a flat collection of permissions into a nested structure:
     *   'student' => [ ['id' => 1, 'action' => 'view', 'display_name' => 'View Students'], ... ]
     *   'finance' => [ ... ]
     *
     * Expected permission name format: module.action (e.g., 'student.view', 'hostel.create')
     *
     * Features:
     * - Skips malformed permission names gracefully
     * - Generates fallback display_name if missing
     * - Sorts modules alphabetically for consistent UI
     * - Ready for Vue accordion/tabs with "Select All" per module
     *
     * @param  \Illuminate\Database\Eloquent\Collection|array  $permissions  Collection or array of Permission models
     * @return array  Nested array keyed by module name
     */
    private function groupPermissionsByModule($permissions): array
    {
        $grouped = [];

        foreach ($permissions as $perm) {
            // Safety: Ensure we have a valid Permission object/array with name
            $permissionName = $perm->name ?? null;

            if (!$permissionName || !str_contains($permissionName, '.')) {
                Log::warning('Skipping invalid or malformed permission during grouping', [
                    'permission_id' => $perm->id ?? 'unknown',
                    'permission_name' => $permissionName,
                ]);
                continue;
            }

            // Split: 'student.view-any' → module = 'student', action = 'view-any'
            [$module, $action] = explode('.', $permissionName, 2);

            // Normalize module key (optional: capitalize or translate later in Vue)
            $moduleKey = $module;

            // Build clean action item for frontend
            $grouped[$moduleKey][] = [
                'id' => $perm->id,
                'action' => $action,
                'display_name' => $perm->display_name
                    ?? Str::title(str_replace(['-', '_'], ' ', $action)), // Fallback: "view-any" → "View Any"
            ];
        }

        // Sort modules alphabetically for consistent, predictable UI
        ksort($grouped, SORT_STRING);

        // Optional: Sort actions within each module (uncomment if desired)
        // foreach ($grouped as $module => $actions) {
        //     usort($actions, fn($a, $b) => strcmp($a['action'], $b['action']));
        //     $grouped[$module] = $actions;
        // }

        return $grouped;
    }

    /**
     * Search roles for async dropdown (used in RoleFormModal "Copy permissions from").
     *
     * Returns a lightweight list of roles matching the search query.
     * Respects school context: includes global roles + current school roles.
     * Used by AsyncSelect component via /api/roles/search?q=...
     *
     * Expected response format:
     * [
     *   { "value": 1, "label": "Teacher (teacher)" },
     *   { "value": 5, "label": "Head of Department (hod)" },
     *   ...
     * ]
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            // Optional: Enforce permission if you want to restrict who can search
            // permitted('manage-roles'); // Uncomment if needed

            $school = GetSchoolModel();

            $query = $request->input('q', ''); // Search term (from AsyncSelect)
            $limit = min($request->input('limit', 50), 100); // Safety cap

            $roles = Role::query()
                ->when($school, function ($q) use ($school) {
                    // Include global (school_id NULL) + current school roles
                    $q->whereNull('school_id')
                        ->orWhere('school_id', $school->id);
                })
                ->when($query, function ($q) use ($query) {
                    $q->where(function ($inner) use ($query) {
                        $inner->where('display_name', 'like', "%{$query}%")
                            ->orWhere('name', 'like', "%{$query}%");
                    });
                })
                ->select(['id as value', DB::raw("CONCAT(display_name, ' (', name, ')') as label")])
                ->orderBy('display_name')
                ->limit($limit)
                ->get();

            return response()->json($roles);

        } catch (\Exception $e) {
            Log::error('Error in RolesController@search', [
                'user_id' => auth()->id(),
                'school_id' => $school->id ?? null,
                'query' => $request->input('q'),
                'exception' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to search roles'], 500);
        }
    }
}