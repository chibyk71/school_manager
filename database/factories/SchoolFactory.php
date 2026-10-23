<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            // Default lifecycle state (ignored when schools.state column is absent).
            'state' => \App\States\School\Draft::$name,
            'email' => $this->faker->companyEmail(),
            'logo' => 'https://via.placeholder.com/150',
            'phone_one' => $this->faker->phoneNumber(),
            'phone_two' => $this->faker->phoneNumber(),
            'slug' => $this->faker->slug(),
        ];
    }
}
