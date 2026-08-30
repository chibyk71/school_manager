<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds admission permissions used by AdmissionPolicy (Phase 3).
 * Safe to re-run: skips existing permission names.
 */
class AdmissionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $names = [
            'admissions.view',
            'admissions.create',
            'admissions.issue',
            'admissions.direct',
            'admissions.bypass',
            'admissions.accept',
            'admissions.decline',
            'admissions.cancel',
            'admissions.expire',
            'admissions.manage-deadlines',
            'admissions.update',
            'admissions.delete',
            'admissions.restore',
            'admissions.force-delete',
        ];

        $now = now();
        foreach ($names as $name) {
            if (DB::table('permissions')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => $name,
                'display_name' => str_replace(['.', '-'], ' ', $name),
                'description' => 'Student lifecycle admission permission',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
