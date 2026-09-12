<?php

namespace Database\Factories;

use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\States\Academic\AcademicSession\Draft;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    public function definition(): array
    {
        $start = now()->startOfYear();
        $end = now()->endOfYear();

        return [
            'id' => (string) Str::uuid(),
            'school_id' => School::factory(),
            'name' => $this->faker->unique()->year() . '/' . ($this->faker->year() + 1),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'state' => Draft::$name,
            'activated_at' => null,
            'closed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['state' => Draft::$name]);
    }

    public function planned(): static
    {
        return $this->state(fn () => ['state' => \App\States\Academic\AcademicSession\Planned::$name]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'state' => \App\States\Academic\AcademicSession\Active::$name,
            'activated_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'state' => \App\States\Academic\AcademicSession\Paused::$name,
            'activated_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'state' => \App\States\Academic\AcademicSession\Closed::$name,
            'activated_at' => now()->subMonths(6),
            'closed_at' => now(),
        ]);
    }

    public function withoutDates(): static
    {
        return $this->state(fn () => [
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
