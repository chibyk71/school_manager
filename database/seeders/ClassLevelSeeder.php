<?php

namespace Database\Seeders;

use App\Models\Academic\ClassLevel;
use App\Models\SchoolSection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassLevelSeeder extends Seeder
{

    private $classLevels = [
        'preschool' => [
            ['name' => 'creche', 'display_name' => 'Creche', 'description' => 'Early childhood care for infants and toddlers.'],
            ['name' => 'nursery_1', 'display_name' => 'Nursery 1', 'description' => 'First stage of preschool education.'],
            ['name' => 'nursery_2', 'display_name' => 'Nursery 2', 'description' => 'Second stage of preschool education.'],
        ],
        'kindergarten' => [
            ['name' => 'kg_1', 'display_name' => 'Kindergarten 1', 'description' => 'Preparatory education before primary school.'],
            ['name' => 'kg_2', 'display_name' => 'Kindergarten 2', 'description' => 'Final stage before primary education.'],
        ],
        'primary_school' => [
            ['name' => 'pri_1', 'display_name' => 'Primary 1', 'description' => 'First year of primary education.'],
            ['name' => 'pri_2', 'display_name' => 'Primary 2', 'description' => 'Second year of primary education.'],
            ['name' => 'pri_3', 'display_name' => 'Primary 3', 'description' => 'Third year of primary education.'],
            ['name' => 'pri_4', 'display_name' => 'Primary 4', 'description' => 'Fourth year of primary education.'],
            ['name' => 'pri_5', 'display_name' => 'Primary 5', 'description' => 'Fifth year of primary education.'],
            ['name' => 'pri_6', 'display_name' => 'Primary 6', 'description' => 'Final year of primary education.'],
        ],
        'junior_secondary_school' => [ // Equivalent to Junior Secondary School (JSS)
            ['name' => 'jss_1', 'display_name' => 'JSS 1', 'description' => 'First year of junior secondary education.'],
            ['name' => 'jss_2', 'display_name' => 'JSS 2', 'description' => 'Second year of junior secondary education.'],
            ['name' => 'jss_3', 'display_name' => 'JSS 3', 'description' => 'Final year of junior secondary education.'],
        ],
        'senior_secondary_school' => [ // Equivalent to Senior Secondary School (SSS)
            ['name' => 'sss_1', 'display_name' => 'SSS 1', 'description' => 'First year of senior secondary education.'],
            ['name' => 'sss_2', 'display_name' => 'SSS 2', 'description' => 'Second year of senior secondary education.'],
            ['name' => 'sss_3', 'display_name' => 'SSS 3', 'description' => 'Final year of senior secondary education.'],
        ],
        'adult_education' => [
            ['name' => 'adult_basic', 'display_name' => 'Basic Literacy', 'description' => 'Literacy program for adults.'],
            ['name' => 'adult_intermediate', 'display_name' => 'Post-Literacy', 'description' => 'Advanced reading and writing for adults.'],
            ['name' => 'adult_continuing', 'display_name' => 'Continuing Education', 'description' => 'Further education for adult learners.'],
            ['name' => 'adult_advanced', 'display_name' => 'Advanced Adult Education', 'description' => 'Higher-level courses for adult students.'],
        ]
    ]; 


    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->classLevels as $level => $classes) {
            $section = SchoolSection::where('name', $level)->first();

            if (!$section) {
                $section = SchoolSection::create([
                    'name' => $level,
                ]);
            }

            foreach ($classes as $class) {
                ClassLevel::create([
                    'name' => $class['name'],
                    'display_name' => $class['display_name'],
                    'description' => $class['description'],
                    'school_section_id' => $section->id,
                ]);
            }
        }
    }
}
