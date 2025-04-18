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
        $roleId = request('role_ids');
        $department->roles()->sync([$roleId]);
        return response()->json([
            'message' => 'Role assigned successfully.',
        ]);
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
        $roles = $department->roles()->get();
        return response()->json([
            'roles' => $roles,
        ]);
    }
}
