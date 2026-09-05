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
            // Explicit, because `users.role` still carries a database default of
            // 'viewer' from the original 2026-04-29 migration and that role was
            // deleted in 1.10.48. A factory user without this got a role that
            // passes no gate and has no home page — which is why the login test
            // landed on /about. Junior chef is the least privileged real role,
            // so a test that quietly depends on privilege fails loudly instead
            // of passing by accident.
            'role' => User::ROLE_JUNIOR_CHEF,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
