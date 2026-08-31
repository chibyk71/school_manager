<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Phase 4 enrollment permissions.
 */
class EnrollmentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'enrollments.view', 'display_name' => 'View Enrollments', 'description' => 'View enrollment records'],
            ['name' => 'enrollments.create', 'display_name' => 'Create Enrollments', 'description' => 'Start new enrollments'],
            ['name' => 'enrollments.edit', 'display_name' => 'Edit Enrollments', 'description' => 'Update incomplete enrollments / biodata'],
            ['name' => 'enrollments.manage_requirements', 'display_name' => 'Manage Enrollment Requirements', 'description' => 'Satisfy or waive enrollment requirements'],
            ['name' => 'enrollments.finalize', 'display_name' => 'Finalize Enrollments', 'description' => 'Finalize enrollment and create Student'],
            ['name' => 'enrollments.delete', 'display_name' => 'Delete Enrollments', 'description' => 'Delete non-active enrollments'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                [
                    'display_name' => $perm['display_name'],
                    'description' => $perm['description'],
                ]
            );
        }
    }
}
