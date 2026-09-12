<?php

namespace Database\Factories\Academic;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\States\Academic\Term\Planned as TermPlanned;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'academic_session_id' => AcademicSession::factory(),
            'name' => fake()->randomElement(['First Term', 'Second Term', 'Third Term']),
            'short_name' => fake()->optional()->randomElement(['1st', '2nd', '3rd']),
            'ordinal_number' => 1,
            'description' => fake()->optional()->sentence(),
            'start_date' => null,
            'end_date' => null,
            'state' => TermPlanned::$name,
            'closed_at' => null,
            'color' => fake()->optional()->hexColor(),
            'options' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => ['state' => TermPlanned::$name, 'closed_at' => null]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'state' => \App\States\Academic\Term\Active::$name,
            'closed_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'state' => \App\States\Academic\Term\Closed::$name,
            'closed_at' => now(),
        ]);
    }
}
