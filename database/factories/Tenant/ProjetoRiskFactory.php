<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\ProjetoRisk;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ProjetoRisk> */
class ProjetoRiskFactory extends Factory
{
    protected $model = ProjetoRisk::class;

    public function definition(): array
    {
        return [
            'projeto_id' => Projeto::query()->inRandomOrder()->value('id') ?? 1,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'probability' => 'medium',
            'impact' => 'medium',
            'severity' => 'medium',
            'status' => 'open',
            'mitigation' => fake()->optional()->sentence(),
            'responsible_id' => User::query()->inRandomOrder()->value('id'),
            'due_date' => fake()->dateTimeBetween('+1 week', '+4 months'),
        ];
    }
}
