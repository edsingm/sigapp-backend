<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\ProjetoDependency;
use App\Models\Tenant\ProjetoMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ProjetoDependency> */
class ProjetoDependencyFactory extends Factory
{
    protected $model = ProjetoDependency::class;

    public function definition(): array
    {
        return [
            'projeto_id' => 1,
            'predecessor_milestone_id' => ProjetoMilestone::factory(),
            'successor_milestone_id' => ProjetoMilestone::factory(),
            'dependency_type' => 'finish_to_start',
            'lag_days' => 0,
        ];
    }
}
