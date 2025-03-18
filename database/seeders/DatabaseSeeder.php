<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Settings\ApplicationTableSeeder;
use Database\Seeders\Settings\BrandingTableSeeder;
use Database\Seeders\Settings\ContactTableSeeder;
use Database\Seeders\Settings\EmailTableSeeder;
use Database\Seeders\Settings\FeesTableSeeder;
use Database\Seeders\Settings\LocalizationTableSeeder;
use Database\Seeders\Settings\MaintenanceTableSeeder;
use Database\Seeders\Settings\RolesTableSeeder;
use Database\Seeders\Settings\SMTPTableSeeder;
use Database\Seeders\Settings\TemplateTableSeeder;
use Database\Seeders\Settings\UserManagementTableSeeder;
use Database\Seeders\Tenant\SMSTableSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        School::factory()->create([
            'name' => 'Test School',
            'email' => 'test@example.com',
            'phone_one' => '123-456-7890',
            'slug' => 'test',
            'logo' => 'test.png',
        ]);

        $this->call(SchoolSectionSeeder::class);
        $this->call(ClassLevelSeeder::class);

        // $this->call(SMSTableSeeder::class);
        // $this->call(ApplicationTableSeeder::class);
        // $this->call(RolesTableSeeder::class);
        // $this->call(EmailTableSeeder::class);
        // $this->call(SMTPTableSeeder::class);
        // $this->call(TemplateTableSeeder::class);
        // $this->call(BrandingTableSeeder::class);
        // $this->call(FeesTableSeeder::class);
        // $this->call(LocalizationTableSeeder::class);
        // $this->call(ContactTableSeeder::class);
        // $this->call(MaintenanceTableSeeder::class);
        // $this->call(UserManagementTableSeeder::class);
    }
}
