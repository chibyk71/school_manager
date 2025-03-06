<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermissionController extends Controller
{
    public function index(Role $role)
    {
        $assignedPermissions = $role->permissions()->pluck('name')->toArray();
        $allPermissions = Permission::pluck('name')->toArray();

        $formattedPermissions = $this->formatPermissions($allPermissions);
        $formattedAssignedPermissions = $this->formatPermissions($assignedPermissions);

        return Inertia::render('UserManagement/Permission', [
            'assignedPermissions' => $formattedAssignedPermissions,
            'allPermissions' => $formattedPermissions,
        ]);
    }

    /**
     * Format permissions into module-based structure.
     */
    private function formatPermissions(array $permissions): array
    {
        $formatted = [];

        foreach ($permissions as $permission) {
            [$action, $module] = explode('-', $permission, 2);

            if (!isset($formatted[$module])) {
                $formatted[$module] = [];
            }

            $formatted[$module][] = $action;
        }

        return $formatted;
    }

}
