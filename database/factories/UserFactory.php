<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'name' => $this->faker->name(),
            'locale' => 'en',
            'is_super_admin' => false,
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function superAdmin(): self
    {
        return $this->state(fn () => ['is_super_admin' => true]);
    }
}
