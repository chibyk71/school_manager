<?php

namespace App\Http\Controllers;

use App\Models\Employee\Department;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = Department::with('roles')->get();
        return inertia('HRM/Department', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request)
    {
        $validated = $request->validated();
        $department = Department::create($validated);

        return redirect()->route('department.index')
            ->with('success', 'Department created successfully.');
    }

    /**z
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        $department->load('roles:id,display_name');
        return response()->json([
            'department' => $department,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $validated = $request->validated();
        $department->update($validated);
        return redirect()->route('department.index')
            ->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        $ids = request('ids');
        $departments = Department::whereIn('id', $ids)->delete();
        return response()->json([
            'message' => 'Departments deleted successfully.',
            'deleted' => $departments,
        ]);
    }

    // assign role to department
    public function assignRole(Department $department)
    {
        $roleIds = request('roles', []); // Accept multiple role IDs as an array
        if (!is_array($roleIds)) {
            return back()->with('error', 'Invalid input. Please provide an array of role IDs.');
        }

        $department->roles()->sync($roleIds); // Sync roles with the department
        return back()
            ->with('success', 'Roles assigned successfully.');
    }

    // get users in department
    public function users(Department $department)
    {
        $users = $department->users()->with('roles:id,display_name')->get();
        return response()->json([
            'users' => $users,
        ]);
    }
    // get roles in department
    public function roles(Department $department)
    {
        $roles = $department->roles()->get(['roles.id', 'roles.name', 'roles.display_name']);
        $roles->transform(function ($item) {
            $item->name = !empty($item->display_name) ? $item->display_name : $item->name;
            unset($item->display_name);
            return $item;
        });
        return response()->json([
            'data' => $roles,
        ]);
    }
}
