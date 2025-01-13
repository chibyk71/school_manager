<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\Team;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * RolesController
 *
 *  This controller handles the creation, updating, and deletion of roles within the application.
 *  Roles define a set of permissions that can be assigned to users.
 *
 *  @package App\Http\Controllers\Settings\School
 *  @author  Alfredo Chibuike <alfredo@alfredchibuike71.com.mx>
 *  @version 1.0
 *  @since   2023-09-12
 */

class RolesController extends Controller
{
    public function index()
    {
        // 
        $school = GetSchoolModel();

        $teams = Team::where('student_id', $school?->id)->get(['id,name']);

        $permissions = Permission::all(['name','id']);

        $roles = Role::where('school_id', $school?->id)->get(['id,name']);

        return Inertia::render('Settings/School/Roles', [
            'permissions' => $permissions,
            'roles' => $roles,
            'teams' => $teams,
        ]);
    }

    public function storeRole(Request $request)
    {
        $school = GetSchoolModel();
        // Save Roles settings
        $validated = $request->validate([
            'role' => 'required|string',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
            'team' => 'sometimes|string|nullable',
            'display_name' => 'string|nullable',
            'description' => 'string|nullable',
        ]);

        // Create or update the role
        $role = Role::updateOrCreate(['name' => $validated['name'], 'school_id' => $school?->id], [
            'display_name' => $request->display_name,
             'description' => $request->description,
        ]);

        // Sync the permissions for the role and team (if provided)
        $role->syncPermissions($validated['permissions'], $validated['team']);

        return redirect()->route('settings.school.roles.index')->with('success', 'Role created successfully.');
    }

    // create a function to assign or remove role from user
    public function manageUserRole(Request $request) {
        // Validate the request data
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $user = User::find($validated['user_id']);

        // Check if the user belongs to the currently active tenant before managing the role
        if (!$user || !$user->belongsToSchool()) {
            return redirect()->route('settings.school.roles.index')->with('error', 'You can only manage roles for users of the current tenant.');
        }

        if ($user->hasRole($validated['role_id'], $validated['team_id'] ?? null)) {
            $user->removeRole($validated['role_id'], $validated['team_id'] ?? null);
            $message = 'Role removed from user successfully.';
        } else {
            $user->addRole($validated['role_id'], $validated['team_id'] ?? null);
            $message = 'Role assigned to user successfully.';
        }

        return redirect()->route('settings.school.roles.index')->with('success', $message);
    }

    // create a function to add permision to user
    public function manageUserPermission(Request $request) {
        // Validate the request data
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_id' => 'required|exists:permissions,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $user = User::find($validated['user_id']);
        $team = Team::find($validated['team_id']);

        // Check if the user belongs to the same school as the team
        if ($team && $user->school_id !== $team->school_id) {
            return redirect()->route('settings.school.roles.index')->with('error', 'User does not belong to the same school as the team.');
        }

        if ($user->hasPermission($validated['permission_id'], $validated['team_id'] ?? null)) {
            $user->removePermissions($validated['permission_id'], $validated['team_id'] ?? null);
            $message = 'Permission removed from user successfully.';
        } else {
            $user->givePermissions($validated['permission_id'], $validated['team_id'] ?? null);
            $message = 'Permission added to user successfully.';
        }

        return redirect()->route('settings.school.roles.index')->with('success', $message);
    }

    public function update(Request $request)
    {
        // Update Roles settings
        $request->validate([
            'name' => 'required|string',
            'permissions' => 'required|array|min:1',
        ]);

        $role = Role::find($request->id);
        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return redirect()->route('settings.school.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role)
    {
        // Delete Roles settings
        $role->delete();

        return redirect()->route('settings.school.roles.index')->with('success', 'Role deleted successfully.');
    }
}
