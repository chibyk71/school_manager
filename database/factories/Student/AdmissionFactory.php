<?php

namespace Database\Factories\Student;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\School;
use App\Models\Student\Admission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Admission> */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => null,
            'application_id' => null,
            'class_level_id' => ClassLevel::factory(),
            'school_section_id' => null,
            'academic_session_id' => AcademicSession::factory(),
            'roll_no' => null,
            'status' => Admission::STATUS_OFFERED,
            'offered_at' => now(),
            'acceptance_deadline' => now()->addDays(14),
            'notes' => null,
            'configs' => null,
        ];
    }
}
