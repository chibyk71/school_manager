<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolSectionSeeder extends Seeder
{
    protected $model = \App\Models\SchoolSection::class;

    private $data = [
        [
            "name" => "preschool",
            "display_name" => "Preschool",
            "description" => "Early childhood education for children before they begin kindergarten."
        ],
        [
            "name" => "kindergarten",
            "display_name" => "Kindergarten",
            "description" => "A preparatory educational stage for young children, typically before first grade."
        ],
        [
            "name" => "primary_school",
            "display_name" => "Primary School",
            "description" => "Elementary education, usually covering grades 1-6."
        ],
        [
            "name" => "junior_secondary_school",
            "display_name" => "Junior Secondary School",
            "description" => "Intermediate education, often covering grades 7-9."
        ],
        [
            "name" => "senior_secondary_school",
            "display_name" => "Senior Secondary School",
            "description" => "Secondary education, typically covering grades 10-12."
        ],
        [
            "name" => "vocational_school",
            "display_name" => "Vocational School",
            "description" => "Education focused on practical skills and trades."
        ],
        [
            "name" => "special_education",
            "display_name" => "Special Education",
            "description" => "Education tailored to students with special needs."
        ],
        [
            "name" => "adult_education",
            "display_name" => "Adult Education",
            "description" => "Education programs for adults seeking further learning or skills development."
        ]
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->data as $item) {
            $this->model::create($item);
        }
    }
}
