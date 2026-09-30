<?php
// database/seeders/Settings/PermissionSeeder.php

namespace Database\Seeders\Settings;

use App\Support\Permission\PermissionCatalogue;
use Illuminate\Database\Seeder;

/**
 * Seeder for creating initial permissions in the system.
 * Permission definitions are application-owned (not administrator CRUD).
 * Catalogue parts live alongside this seeder and are merged then synced.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = array_merge(
            require __DIR__ . '/permission_catalogue_part1.php',
            require __DIR__ . '/permission_catalogue_part2.php',
            require __DIR__ . '/permission_catalogue_part3.php',
        );

        // Application-owned catalogue sync (idempotent; no administrator CRUD).
        PermissionCatalogue::sync($permissions);
    }
}
