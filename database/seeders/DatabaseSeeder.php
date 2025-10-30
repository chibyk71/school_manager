<?php

namespace Database\Seeders;

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
        // -----------------------------------------------------------------
        // 1. ROLES & PERMISSIONS (must be first – everything else references them)
        // -----------------------------------------------------------------
        $this->callWithLog(RolesTableSeeder::class);

        // -----------------------------------------------------------------
        // 2. GLOBAL SETTINGS (tenant-agnostic defaults)
        // -----------------------------------------------------------------
        $this->callWithLog(\Database\Seeders\Settings\ApplicationTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\BrandingTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\ContactTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\EmailTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\FeesTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\LocalizationTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\MaintenanceTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\SMTPTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\TemplateTableSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\UserManagementSeeder::class);
        $this->callWithLog(\Database\Seeders\Settings\SMSSeeder::class);   // <-- NEW

        // -----------------------------------------------------------------
        // 3. OPTIONAL: Demo data / factories (uncomment for local dev)
        // -----------------------------------------------------------------

        // $this->callWithLog(\Database\Seeders\SchoolSectionSeeder::class);
        // $this->callWithLog(\Database\Seeders\ClassLevelSeeder::class);
        // $this->callWithLog(\Database\Seeders\DepartmentSeeder::class);

        // Create a demo school + admin
        $school = \App\Models\School::factory()->create([
            'name'      => 'Demo Academy',
            'slug'      => 'demo',
            'code'      => 'DA',
            'email'     => 'admin@demo.academy',
            'phone_one' => '08012345678',
        ]);

        \App\Models\User::factory()->create([
            'name'  => 'Demo Admin',
            'email' => 'admin@demo.academy',
            'school_id' => $school->id,
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
