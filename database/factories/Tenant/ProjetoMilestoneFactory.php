<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\ProjetoMilestone;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ProjetoMilestone> */
class ProjetoMilestoneFactory extends Factory
{
    protected $model = ProjetoMilestone::class;

    public function definition(): array
    {
        return [
            'projeto_id' => Projeto::query()->inRandomOrder()->value('id') ?? 1,
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => 'pending',
            'planned_start' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'planned_end' => fake()->dateTimeBetween('+2 months', '+4 months'),
            'weight' => fake()->numberBetween(1, 5),
            'position' => fake()->numberBetween(0, 10),
            'is_critical' => false,
            'responsible_id' => User::query()->inRandomOrder()->value('id'),
        ];
    }
}
