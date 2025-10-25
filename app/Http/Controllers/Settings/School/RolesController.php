<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing roles in a single-tenant school system.
 */
class RolesController extends Controller
{
    /**
     * Display a listing of roles.
     *
     * Retrieves roles for the active school or global roles and renders the view.
     *
     * @return \Inertia\Response The Inertia response with roles data.
     *
     * @throws \Exception If role retrieval fails or no active school is found.
     */
    public function index()
    {
        try {
            permitted('manage-roles');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $roles = Role::where(function ($query) use ($school) {
                $query->whereNull('school_id')
                      ->orWhere('school_id', $school->id);
            })->get(['id', 'name', 'display_name', 'description']);

            return Inertia::render('Settings/School/Roles', [
                'roles' => $roles,
            ], 'resources/js/Pages/Settings/School/Roles.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch roles: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load roles.');
        }
    }

    /**
     * Store a new role.
     *
     * Creates a role for the active school with validated data.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If role creation fails.
     */
    public function store(Request $request)
    {
        try {
            permitted('manage-roles');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,NULL,id,school_id,' . ($school->id ?? 'NULL'),
                'display_name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            Role::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'],
                'school_id' => $school->id,
            ]);

            return redirect()
                ->route('settings.roles.index')
                ->with('success', 'Role created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create role: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing role.
     *
     * Updates a role and syncs its permissions for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @param Role $role The role to update.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If role update fails.
     */
    public function update(Request $request, Role $role)
    {
        try {
            permitted('manage-roles');

            $school = GetSchoolModel();
            if (!$school || $role->school_id !== $school->id) {
                abort(403, 'Unauthorized access to role.');
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $role->id . ',id,school_id,' . $school->id,
                'display_name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role->update([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'],
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->sync($validated['permissions']);
            }

            return redirect()
                ->route('settings.roles.index')
                ->with('success', 'Role updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update role: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * Delete a role.
     *
     * Deletes a role if it has no assigned users for the active school.
     *
     * @param Role $role The role to delete.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Exception If role deletion fails.
     */
    public function destroy(Role $role)
    {
        try {
            permitted('manage-roles');

            $school = GetSchoolModel();
            if (!$school || $role->school_id !== $school->id) {
                abort(403, 'Unauthorized access to role.');
            }

            if ($role->users()->count() > 0) {
                return redirect()->route('settings.roles.index')->with('error', 'Cannot delete a role with assigned users.');
            }

            $role->delete();

            return redirect()
                ->route('settings.roles.index')
                ->with('success', 'Role deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete role: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * Assign permissions to a role.
     *
     * Adds permissions to a role for the active school without detaching existing ones.
     *
     * @param Request $request The incoming HTTP request.
     * @param Role $role The role to assign permissions to.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If permission assignment fails.
     */
    public function assignPermissions(Request $request, Role $role)
    {
        try {
            permitted('manage-roles');

            $school = GetSchoolModel();
            if (!$school || $role->school_id !== $school->id) {
                abort(403, 'Unauthorized access to role.');
            }

            $validated = $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role->permissions()->syncWithoutDetaching($validated['permissions']);

            return redirect()
                ->route('settings.roles.index')
                ->with('success', 'Permissions assigned successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to assign permissions: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to assign permissions: ' . $e->getMessage());
        }
    }

    /**
     * Remove permissions from a role.
     *
     * Removes specific permissions from a role for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @param Role $role The role to remove permissions from.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If permission removal fails.
     */
    public function removePermissions(Request $request, Role $role)
    {
        try {
            permitted('manage-roles');

            $school = GetSchoolModel();
            if (!$school || $role->school_id !== $school->id) {
                abort(403, 'Unauthorized access to role.');
            }

            $validated = $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role->permissions()->detach($validated['permissions']);

            return redirect()
                ->route('settings.roles.index')
                ->with('success', 'Permissions removed successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to remove permissions: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to remove permissions: ' . $e->getMessage());
        }
    }

    /**
     * Sync permissions for a role.
     *
     * Replaces all permissions for a role with the provided ones for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @param Role $role The role to sync permissions for.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If permission sync fails.
     */
    public function syncPermissions(Request $request, Role $role)
    {
        try {
            permitted('manage-roles');

            $school = GetSchoolModel();
            if (!$school || $role->school_id !== $school->id) {
                abort(403, 'Unauthorized access to role.');
            }

            $validated = $request->validate([
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role->permissions()->sync($validated['permissions'] ?? []);

            return redirect()
                ->route('settings.roles.index')
                ->with('success', 'Permissions updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to sync permissions: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to sync permissions: ' . $e->getMessage());
        }
    }
}
