<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Common\RolesEnum;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @phpstan-extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => 'pt-BR',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(RolesEnum::ADMIN->value);
        });
    }

    public function withPassword(string $password): static
    {
        return $this->state(fn (): array => [
            'password' => Hash::make($password),
        ]);
    }
}
