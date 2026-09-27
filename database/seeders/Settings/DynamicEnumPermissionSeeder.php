<?php

namespace Database\Seeders\Settings;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Dynamic Enum Phase 7 permissions (scope-neutral capabilities).
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\Settings\\DynamicEnumPermissionSeeder
 */
class DynamicEnumPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'dynamic-enums.view',
                'display_name' => 'View Dynamic Enums',
                'description' => 'View Dynamic Enum definitions and effective configuration',
            ],
            [
                'name' => 'dynamic-enums.manage',
                'display_name' => 'Manage Dynamic Enums',
                'description' => 'Manage Dynamic Enum configuration in the current authorization context (tenant or school)',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        // Remove obsolete scope-encoded capability if present from Phase 4.
        Permission::query()->where('name', 'dynamic-enums.manageGlobals')->delete();
    }
}
