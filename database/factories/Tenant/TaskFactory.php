<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'related_type' => null,
            'related_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.5)->paragraph(),
            'assigned_to' => User::factory(),
            'status' => 'pendente',
            'priority' => fake()->randomElement(['baixa', 'media', 'alta']),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'completed_at' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function pendente(): static
    {
        return $this->state(fn (): array => [
            'status' => 'pendente',
            'completed_at' => null,
        ]);
    }

    public function concluida(): static
    {
        return $this->state(fn (): array => [
            'status' => 'concluida',
            'completed_at' => now(),
        ]);
    }

    public function atrasada(): static
    {
        return $this->state(fn (): array => [
            'status' => 'pendente',
            'completed_at' => null,
            'due_date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
        ]);
    }
}
