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

        $d = [
            'Academic' => [
                'Sciences' => [
                    'Mathematics',
                    'Physics',
                    'Chemistry',
                    'Biology',
                    'Agricultural Science',
                ],
                'Humanities' => [
                    'English Language',
                    'Literature in English',
                    'History',
                    'Geography',
                    'Government',
                    'Religious Studies (Christian Religious Studies)',
                    'Religious Studies (Islamic Religious Studies)',
                    'Social Studies',
                ],
                'Languages' => [
                    'Hausa Language',
                    'Igbo Language',
                    'Yoruba Language',
                    'French Language',
                    'Other Foreign Languages',
                ],
                'Vocational/Technical Studies' => [
                    'Technical Drawing',
                    'Woodwork',
                    'Metalwork',
                    'Electronics',
                    'Auto Mechanics',
                    'Home Economics (Food & Nutrition)',
                    'Home Economics (Clothing & Textiles)',
                    'Computer Studies/ICT',
                    'Business Studies (Commerce)',
                    'Business Studies (Economics)',
                    'Business Studies (Accounting)',
                    'Business Studies (Office Practice)',
                ],
                'Arts and Creative Studies' => [
                    'Fine Arts',
                    'Music',
                    'Drama/Theatre Arts',
                ],
                'Physical and Health Education' => [
                    'Physical Education',
                    'Health Education',
                ],
            ],
            'Administrative' => [
                'School Administration' => [
                    'Principal\'s Office',
                    'Vice Principal\'s Office (Academic)',
                    'Vice Principal\'s Office (Administration)',
                    'School Secretary\'s Office',
                ],
                'Finance' => [
                    'Bursary/Accounts Department',
                ],
                'Student Support' => [
                    'Guidance and Counseling Unit',
                    'Examinations and Records Department',
                    'Library Department',
                    'ICT/MIS Department',
                    'Student Affairs/Welfare Department',
                    'Boarding House Department',
                ],
                'Operations' => [
                    'Security Department',
                    'Maintenance Department (Works & Services)',
                    'Transport Department',
                    'Catering Department',
                ],
                'External Relations' => [
                    'Public Relations/Information Department',
                ],
            ],
        ];

        // clear the table before seeding
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::table('departments')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        $departments = [
            // Academic Departments
            'Academic' => [
                [
                    'name' => 'Sciences',
                    'description' => 'Departments focused on scientific disciplines such as Physics, Chemistry, and Biology.',
                ],
                [
                    'name' => 'Humanities',
                    'description' => 'Departments covering subjects like History, Geography, and Religious Studies.',
                ],
                [
                    'name' => 'Vocational/Technical Studies',
                    'description' => 'Departments offering practical and technical skills like Woodwork, Electronics, and Business Studies.',
                ],
                [
                    'name' => 'Arts and Creative Studies',
                    'description' => 'Departments for creative disciplines such as Fine Arts, Music, and Drama.',
                ],
                [
                    'name' => 'Physical and Health Education',
                    'description' => 'Departments promoting physical fitness and health education.',
                ],
                [
                    'name' => 'Languages',
                    'description' => 'Departments teaching local and foreign languages such as Yoruba, French, and Hausa.',
                ],
            ],

            // Administrative & Support Departments
            'Administrative & Support' => [
                [
                    'name' => 'Administration',
                    'description' => 'Departments managing school administration and leadership offices.',
                ],
                [
                    'name' => 'Bursary/Accounts Department',
                    'description' => 'Department responsible for financial management and accounting.',
                ],
                [
                    'name' => 'Guidance and Counseling Unit',
                    'description' => 'Unit providing student support and counseling services.',
                ],
                [
                    'name' => 'Examinations and Records Department',
                    'description' => 'Department handling examinations and maintaining student records.',
                ],
                [
                    'name' => 'Library Department',
                    'description' => 'Department managing library resources and services.',
                ],
                [
                    'name' => 'ICT/MIS Department',
                    'description' => 'Department overseeing information technology and management systems.',
                ],
                [
                    'name' => 'Student Affairs/Welfare Department',
                    'description' => 'Department addressing student welfare and extracurricular activities.',
                ],
                [
                    'name' => 'Boarding House Department',
                    'description' => 'Department managing boarding facilities and student accommodations.',
                ],
                [
                    'name' => 'Security Department',
                    'description' => 'Department ensuring the safety and security of the school premises.',
                ],
                [
                    'name' => 'Maintenance Department (Works & Services)',
                    'description' => 'Department responsible for maintaining school infrastructure and facilities.',
                ],
                [
                    'name' => 'Transport Department',
                    'description' => 'Department managing school transportation services.',
                ],
                [
                    'name' => 'Catering Department',
                    'description' => 'Department providing food and catering services.',
                ],
                [
                    'name' => 'Public Relations/Information Department',
                    'description' => 'Department handling public relations and communication.',
                ],
            ],
        ];

        foreach ($departments as $category => $subDepartments) {
            foreach ($subDepartments as $name) {
                DB::table('departments')->insert([
                    'name' => $name['name'],
                    'category' => $category,
                    'description' => $name['description'] ?? null,
                    'effective_date' => now(),
                    'slug' => Str::slug($name['name']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
