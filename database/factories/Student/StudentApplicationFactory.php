<?php

namespace Database\Factories\Student;

use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\Student\StudentApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StudentApplication> */
class StudentApplicationFactory extends Factory
{
    protected $model = StudentApplication::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_session_id' => null,
            'school_section_id' => null,
            'class_level_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => null,
            'date_of_birth' => fake()->dateTimeBetween('-12 years', '-5 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'source' => 'admin_direct',
            'status' => 'pending',
            'application_number' => 'APP-'.now()->year.'-'.Str::upper(Str::random(6)),
            'application_token' => Str::random(64),
            'submitted_at' => now(),
            'student_id' => null,
        ];
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn () => ['school_id' => $school->id]);
    }

    public function forSession(AcademicSession $session): static
    {
        return $this->state(fn () => [
            'school_id' => $session->school_id,
            'academic_session_id' => $session->id,
        ]);
    }
}
