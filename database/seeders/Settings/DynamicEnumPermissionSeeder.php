<?php

namespace Database\Seeders\Settings;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Dynamic Enum Phase 4 permissions.
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
                'display_name' => 'Manage School Dynamic Enums',
                'description' => 'Manage school-level Dynamic Enum overrides and school-only options',
            ],
            [
                'name' => 'dynamic-enums.manageGlobals',
                'display_name' => 'Manage Tenant Dynamic Enums',
                'description' => 'Manage tenant/default Dynamic Enum configuration and requiredness',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
