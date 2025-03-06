<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
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
         $school = GetSchoolModel();

         $roles = Role::where(function ($query) use ($school) {
             $query->whereNull('school_id');
             if ($school) {
                 $query->orWhere('school_id', $school->id);
             }
         })->get(['id', 'name']);

         return Inertia::render('UserManagement/Roles', ['roles' => $roles]);
     }

     public function store(Request $request)
     {
         $school = GetSchoolModel();

         $validated = $request->validate([
             'name' => 'required|string|unique:roles,name',
             'display_name' => 'nullable|string',
             'description' => 'nullable|string',
         ]);

         $role = Role::create([
             'name' => $validated['name'],
             'display_name' => $validated['display_name'],
             'description' => $validated['description'],
             'school_id' => $school?->id,
         ]);

         return redirect()->route('settings.school.roles.index')->with('success', 'Role created successfully.');
     }


     public function update(Request $request, Role $role)
     {
         $validated = $request->validate([
             'name' => 'required|string|unique:roles,name,' . $role->id,
             'display_name' => 'nullable|string',
             'description' => 'nullable|string',
             'permissions' => 'array',
         ]);

         $role->update([
             'name' => $validated['name'],
             'display_name' => $validated['display_name'],
             'description' => $validated['description'],
         ]);

         if ($request->has('permissions')) {
             $role->permissions()->sync($validated['permissions']);
         }

         return redirect()->route('settings.school.roles.index')->with('success', 'Role updated successfully.');
     }

     public function destroy(Role $role)
     {
         if ($role->users()->count() > 0) {
             return redirect()->route('settings.school.roles.index')->with('error', 'Cannot delete a role with assigned users.');
         }

         $role->delete();

         return redirect()->route('settings.school.roles.index')->with('success', 'Role deleted successfully.');
     }

     /**
      * Assign permissions to a role
      */
     public function assignPermissions(Request $request, Role $role)
     {
         $validated = $request->validate([
             'permissions' => 'required|array',
             'permissions.*' => 'exists:permissions,id',
         ]);

         $role->permissions()->syncWithoutDetaching($validated['permissions']);

         return redirect()->route('settings.school.roles.index')->with('success', 'Permissions assigned successfully.');
     }

     /**
      * Remove specific permissions from a role
      */
     public function removePermissions(Request $request, Role $role)
     {
         $validated = $request->validate([
             'permissions' => 'required|array',
             'permissions.*' => 'exists:permissions,id',
         ]);

         $role->permissions()->detach($validated['permissions']);

         return redirect()->route('settings.school.roles.index')->with('success', 'Permissions removed successfully.');
     }

     /**
      * Sync role permissions (replace all assigned permissions)
      */
     public function syncPermissions(Request $request, Role $role)
     {
         $validated = $request->validate([
             'permissions' => 'array',
             'permissions.*' => 'exists:permissions,id',
         ]);

         $role->permissions()->sync($validated['permissions'] ?? []);

         return redirect()->route('settings.school.roles.index')->with('success', 'Permissions updated successfully.');
     }
 }

