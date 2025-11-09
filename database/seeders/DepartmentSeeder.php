<?php

namespace Database\Seeders;

use App\Models\Employee\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Administration', 'category' => 'administration'],
            ['name' => 'Academic Affairs', 'category' => 'academic'],
            ['name' => 'Guidance & Counseling', 'category' => 'guidance_counseling'],
            ['name' => 'Library', 'category' => 'library'],
            ['name' => 'ICT', 'category' => 'ict'],
            ['name' => 'Sports & Physical Education', 'category' => 'sports'],
            ['name' => 'Welfare', 'category' => 'welfare'],
            ['name' => 'Transport', 'category' => 'transport'],
            ['name' => 'Security', 'category' => 'security'],
            ['name' => 'Finance & Accounts', 'category' => 'finance'],
            ['name' => 'Maintenance & Works', 'category' => 'maintenance'],
            ['name' => 'Parent', 'category' => 'parent'],
            ['name' => 'Student', 'category' => 'student'],
            ['name' => 'School Clinic / Health', 'category' => 'clinic'],
            ['name' => 'Hostel Management', 'category' => 'hostel'],
            ['name' => 'Kitchen / Catering', 'category' => 'kitchen'],
            ['name' => 'Cleaning & Sanitation', 'category' => 'cleaning'],
            ['name' => 'Gardening & Grounds', 'category' => 'grounds'],
            ['name' => 'Admissions & Records', 'category' => 'admissions_records'],
            ['name' => 'Human Resource', 'category' => 'human_resource']
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['school_id' => null, 'name' => $dept['name']], // system-level defaults
                [
                    'category' => $dept['category'],
                    'description' => null,
                    'effective_date' => now(),
                ]
            );
        }
    }
}