<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'role_id'           => Role::idByName(Role::STAFF),
            'department_id'     => Department::factory(),
            'join_date'         => now()->toDateString(),
        ];
    }

    /**
     * Create a staff user (same as default — kept for backwards compatibility in tests).
     */
    public function staff(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::idByName(Role::STAFF),
        ]);
    }
}
