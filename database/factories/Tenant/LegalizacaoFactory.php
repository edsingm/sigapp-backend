<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalizacaoFactory extends Factory
{
    protected $model = Legalizacao::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'nome' => fake()->sentence(3),
            'status' => fake()->randomElement(['planejado', 'em_andamento', 'concluido', 'cancelado']),
            'data_inicio_planejada' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'data_fim_planejada' => fake()->dateTimeBetween('+2 months', '+6 months'),
            'percentual_concluido' => fake()->numberBetween(0, 100),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function concluida(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'concluido',
            'percentual_concluido' => 100,
        ]);
    }

    public function emAndamento(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'em_andamento',
        ]);
    }

    public function atrasada(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'em_andamento',
        ]);
    }
}
