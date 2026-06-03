<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<Negociacao>
 */
class NegociacaoFactory extends Factory
{
    protected $model = Negociacao::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'status' => 'em_andamento',
            'proposal_value' => fake()->randomFloat(2, 100000, 10000000),
            'business_model' => fake()->randomElement(['compra', 'parceria', 'permuta']),
            'started_at' => now(),
            'closed_at' => null,
            'notes' => fake()->optional(0.3)->paragraph(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function emAndamento(): static
    {
        return $this->state(fn (): array => [
            'status' => 'em_andamento',
            'closed_at' => null,
        ]);
    }

    public function fechada(): static
    {
        return $this->state(fn (): array => [
            'status' => 'fechada',
            'closed_at' => now(),
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (): array => [
            'status' => 'cancelada',
            'closed_at' => now(),
        ]);
    }
}
