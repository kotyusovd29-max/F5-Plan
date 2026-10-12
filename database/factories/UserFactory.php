<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'role' => User::ROLE_EMPLOYEE,
            'rop_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if ($user->is_admin) {
                $user->role = User::ROLE_ADMIN;
                $user->rop_id = null;
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
            'role' => User::ROLE_ADMIN,
            'rop_id' => null,
        ]);
    }

    public function rop(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => User::ROLE_ROP,
            'rop_id' => null,
        ]);
    }

    public function employee(?User $rop = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => User::ROLE_EMPLOYEE,
            'rop_id' => $rop?->id,
        ]);
    }
}
