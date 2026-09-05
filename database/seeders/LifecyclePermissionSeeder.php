<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LifecyclePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'lifecycle-reports.view',
                'display_name' => 'View Lifecycle Reports',
                'description' => 'View and export student lifecycle operational reports',
            ],
            [
                'name' => 'lifecycle-ops.view',
                'display_name' => 'View Lifecycle Operations',
                'description' => 'View lifecycle needs-attention and operational queues',
            ],
        ];

        foreach ($permissions as $perm) {
            $exists = DB::table('permissions')->where('name', $perm['name'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('permissions')->insert([
                'id' => (string) Str::uuid(),
                'name' => $perm['name'],
                'display_name' => $perm['display_name'],
                'description' => $perm['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
