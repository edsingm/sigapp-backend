<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Models\Tenant\ViabilidadeScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<ViabilidadeScenario>
 */
class ViabilidadeScenarioFactory extends Factory
{
    protected $model = ViabilidadeScenario::class;

    public function definition(): array
    {
        return [
            'viabilidade_id' => Viabilidade::factory(),
            'name' => fake()->sentence(3),
            'scenario_type' => 'custom',
            'status' => 'draft',
            'premises_snapshot' => ['overrides' => []],
            'results' => null,
            'formula_version' => 'viabilidade-v1',
            'input_hash' => null,
            'created_by' => User::factory(),
        ];
    }
}
