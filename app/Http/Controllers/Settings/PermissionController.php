<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing permissions in a single-tenant school system.
 */
class PermissionController extends Controller
{
    /**
     * Display permissions for a role.
     *
     * Retrieves all permissions and assigned permissions for a role, formats them, and renders the view.
     *
     * @param Role $role The role to display permissions for.
     * @return \Inertia\Response The Inertia response with permissions data.
     *
     * @throws \Exception If role or permission retrieval fails or no active school is found.
     */
    public function index(Role $role)
    {
        try {
            permitted('manage-permissions');

            $school = GetSchoolModel();
            if (!$school || $role->school_id !== $school->id) {
                abort(403, 'Unauthorized access to role.');
            }

            $assignedPermissions = $role->permissions()->pluck('name')->toArray();
            $allPermissions = Permission::pluck('name')->toArray();

            $formattedPermissions = $this->formatPermissions($allPermissions);
            $formattedAssignedPermissions = $this->formatPermissions($assignedPermissions);

            return Inertia::render('Settings/School/Permission', [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                ],
                'assignedPermissions' => $formattedAssignedPermissions,
                'allPermissions' => $formattedPermissions,
            ], 'resources/js/Pages/Settings/School/Permission.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch permissions: ' . $e->getMessage());
            return redirect()->route('settings.roles.index')->with('error', 'Failed to load permissions.');
        }
    }

    /**
     * Format permissions into a module-based structure.
     *
     * Groups permissions by module (e.g., 'create-school' becomes module 'school', action 'create').
     *
     * @param array $permissions Array of permission names.
     * @return array Formatted permissions grouped by module.
     *
     * @throws \InvalidArgumentException If permission format is invalid.
     */
    private function formatPermissions(array $permissions): array
    {
        $formatted = [];

        foreach ($permissions as $permission) {
            if (!str_contains($permission, '-')) {
                Log::warning("Invalid permission format: {$permission}");
                continue;
            }

            [$action, $module] = explode('-', $permission, 2);

            if (!isset($formatted[$module])) {
                $formatted[$module] = [];
            }

            $formatted[$module][] = $action;
        }

        return $formatted;
    }
}
