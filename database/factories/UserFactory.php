<?php

namespace Database\Factories;

use App\Enums\Auth\AccountLifecycle;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName().'_'.Str::lower(Str::random(4)),
            'password' => static::$password ??= Hash::make('password'),
            'lifecycle' => AccountLifecycle::Active->value,
            'force_password_change' => false,
            'auth_email_verified_at' => null,
            'auth_email_enabled' => false,
            'auth_phone' => null,
            'auth_phone_verified_at' => null,
            'auth_phone_enabled' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->profile()->exists()) {
                return;
            }

            Profile::query()->create([
                'user_id' => $user->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->safeEmail(),
            ]);
        });
    }

    public function provisioned(): static
    {
        return $this->state(fn () => [
            'password' => null,
            'lifecycle' => AccountLifecycle::Provisioned->value,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'password' => static::$password ??= Hash::make('password'),
            'lifecycle' => AccountLifecycle::Active->value,
        ]);
    }

    public function forcePasswordChange(): static
    {
        return $this->state(fn () => [
            'force_password_change' => true,
        ]);
    }

    public function withVerifiedEmailLogin(): static
    {
        return $this->state(fn () => [
            'auth_email_verified_at' => now(),
            'auth_email_enabled' => true,
        ]);
    }
}
