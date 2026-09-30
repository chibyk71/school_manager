<?php
// database/seeders/Settings/PermissionSeeder.php

namespace Database\Seeders\Settings;

use App\Support\Permission\PermissionCatalogue;
use Illuminate\Database\Seeder;

/**
 * Seeder for creating initial permissions in the system.
 * Permissions are global (not school-specific) for MVP, but can be assigned to school-scoped roles.
 * Use Laratrust to attach these to roles.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Grouped permissions for better organization and readability
        $permissions = [

            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'description' => 'Access the main dashboard'],
            ['name' => 'dashboard.edit', 'display_name' => 'Edit Dashboard', 'description' => 'Edit dashboard widgets'],

            // Your single settings permission
            ['name' => 'settings.manage', 'display_name' => 'Manage Settings', 'description' => 'Full access to all settings pages'],

            // dynamic enums (Phase 4)
            ['name' => 'dynamic-enums.view', 'display_name' => 'View Dynamic Enums', 'description' => 'View Dynamic Enum definitions and effective configuration'],
            ['name' => 'dynamic-enums.manage', 'display_name' => 'Manage Dynamic Enums', 'description' => 'Manage Dynamic Enum configuration in the current authorization context (tenant or school)'],

            // Schools (Tenant Management)
            ['name' => 'schools.view-any', 'display_name' => 'View All Schools', 'description' => 'Access the list of schools'],
            ['name' => 'schools.view', 'display_name' => 'View School', 'description' => 'Access The detail of individual school'],
            ['name' => 'school.create', 'display_name' => 'Create School', 'description' => 'Create a new school'],
            ['name' => 'school.update', 'display_name' => 'Update School', 'description' => 'Update an existing school'],
            ['name' => 'school.delete', 'display_name' => 'Delete School', 'description' => 'Delete a school'],
            ['name' => 'school.forceDelete', 'display_name' => 'Force Delete School', 'description' => 'Force delete a school'],
