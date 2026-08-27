<?php

namespace Database\Seeders\Settings;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seeds Laratrust permissions for the Student Promotion module.
 * Safe to run repeatedly (updateOrCreate by name).
 */
class PromotionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'promotions.view', 'display_name' => 'View Promotions', 'description' => 'View promotion batches and student outcomes'],
            ['name' => 'promotions.create', 'display_name' => 'Create Promotion Batches', 'description' => 'Manually create promotion batches for an academic session'],
            ['name' => 'promotions.review', 'display_name' => 'Review Promotions', 'description' => 'Review student recommendations and override decisions'],
            ['name' => 'promotions.approve', 'display_name' => 'Approve Promotion Batches', 'description' => 'Approve a promotion batch for execution'],
            ['name' => 'promotions.execute', 'display_name' => 'Execute Promotions', 'description' => 'Execute an approved promotion batch'],
            ['name' => 'promotions.cancel', 'display_name' => 'Cancel Promotion Batches', 'description' => 'Cancel a promotion batch before or during processing'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
