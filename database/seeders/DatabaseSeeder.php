<?php

namespace Database\Seeders;

use Database\Seeders\Settings\PermissionSeeder;
use Database\Seeders\Settings\DynamicEnumPermissionSeeder;
use Database\Seeders\Settings\RolesTableSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Master seeder – orchestrates every piece of static data.
 *
 * Run with:
 *   php artisan db:seed
 * or
 *   php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Log::info('DatabaseSeeder started.');

        // -----------------------------------------------------------------
        // 1. Core settings defaults
        // -----------------------------------------------------------------
        $this->callWithLog(\Database\Seeders\Settings\SettingsDefaultsSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\ApplicationSettingsDefaultsSeeder::class);

        // -----------------------------------------------------------------
        // 2. Structural seeders (sections, class levels, departments, roles)
        // -----------------------------------------------------------------
        $this->callWithLog(\Database\Seeders\SchoolSectionSeeder::class);
        $this->callWithLog(\Database\Seeders\ClassLevelSeeder::class);
        $this->callWithLog(\Database\Seeders\DepartmentSeeder::class);
        $this->callWithLog(RolesTableSeeder::class);
        $this->callWithLog(PermissionSeeder::class);
        $this->callWithLog(DynamicEnumPermissionSeeder::class);
        $this->callWithLog(\Database\Seeders\ApplicationPermissionSeeder::class);
        $this->callWithLog(\Database\Seeders\AdmissionPermissionSeeder::class);

        $this->callWithLog(\Database\Seeders\DynamicEnumSeeder::class);

        // assign all roles to admin role
        $role = \App\Models\Role::query()->where('name', 'admin')->first();
        $role->permissions()->sync(\App\Models\Permission::all()->pluck('id'));

        // create a default admin user if not exists
        \App\Models\User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin',
            'password' => bcrypt('password'),
        ])->addRole('admin');

        Log::info('DatabaseSeeder finished successfully.');
    }

    /**
     * Helper – call a seeder and log it.
     *
     * @param  class-string<\Illuminate\Database\Seeder>  $seeder
     */
    private function callWithLog(string $seeder): void
    {
        $name = class_basename($seeder);
        Log::info("Running seeder: {$name}");
        $this->call($seeder);
        Log::info("Finished seeder: {$name}");
    }
}
