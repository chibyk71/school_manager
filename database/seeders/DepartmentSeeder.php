<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            // Academic Departments
            'Academic' => [
                'Mathematics',
                'English Language',
                'Science',
                'Social Studies',
                'Basic Technology',
                'ICT/Computer Studies',
                'Business Studies',
                'Agricultural Science',
                'Home Economics',
                'Physical and Health Education',
                'Religious Studies',
                'Civic Education',
                'Languages',
                'Creative Arts',
                'Music',
                'Vocational Studies',
            ],

            // Administrative & Support Departments
            'Adninistrative & Support' => [
                'Administration',
                'Accounts/Bursary',
                'Human Resources',
                'Exams and Records',
                'Admissions',
                'Library Services',
                'Guidance and Counselling',
                'Security',
                'Maintenance/Works',
                'ICT Support',
                'Medical/Health Services',
                'Transport',
                'Boarding/Hostel Services',
                'Cleaning/Janitorial',
                'School Store/Supplies',
            ]
        ];

        foreach ($departments as $category => $subDepartments) {
            foreach ($subDepartments as $name) {
                DB::table('departments')->insert([
                    'name' => $name,
                    'category' => $category,
                    'description' => null,
                    'effective_date' => now(),
                    'slug' => Str::slug($name),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
