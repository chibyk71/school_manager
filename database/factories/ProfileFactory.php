<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->optional()->firstName(),
            'gender' => fake()->optional()->randomElement(['male', 'female', 'other']),
            'date_of_birth' => fake()->optional()->date(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'user_id' => null,
        ];
    }

    public function withEmail(string $email): static
    {
        return $this->state(fn () => ['email' => strtolower($email)]);
    }
}
