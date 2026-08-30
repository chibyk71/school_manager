<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds application permissions used by StudentApplicationPolicy.
 * Safe to re-run: skips existing permission names.
 */
class ApplicationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $names = [
            'applications.view',
            'applications.create',
            'applications.review',
            'applications.approve',
            'applications.reject',
            'applications.delete',
            'applications.restore',
        ];

        $now = now();
        foreach ($names as $name) {
            $exists = DB::table('permissions')->where('name', $name)->exists();
            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => $name,
                'display_name' => str_replace('.', ' ', $name),
                'description' => 'Student lifecycle application permission',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
