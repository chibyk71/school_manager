<?php

namespace Database\Factories\Student;

use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => null, // Phase 4: nullable until finalize
            'academic_session_id' => AcademicSession::factory(),
            'admission_id' => null,
            'status' => Enrollment::STATUS_DRAFT,
            'notes' => null,
            'meta' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_DRAFT,
            'student_id' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'student_id' => null,
        ]);
    }

    public function withStudent(): static
    {
        return $this->state(fn () => [
            'student_id' => Student::factory(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_ACTIVE,
            'student_id' => Student::factory(),
            'started_at' => now()->subDays(3),
            'activated_at' => now(),
        ]);
    }
}
