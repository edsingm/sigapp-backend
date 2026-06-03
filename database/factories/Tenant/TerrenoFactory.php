<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @phpstan-extends Factory<Terreno>
 */
class TerrenoFactory extends Factory
{
    protected $model = Terreno::class;

    public function definition(): array
    {
        $area = fake()->randomFloat(2, 500, 50000);

        return [
            'nome' => 'Terreno '.Str::random(6),
            'responsavel_id' => User::factory(),
            'endereco' => fake()->streetAddress(),
            'estado' => fake()->randomElement(['SP', 'RJ', 'MG', 'PR', 'SC', 'BA']),
            'cidade_code' => (string) fake()->numberBetween(1000000, 9999999),
            'polygon_coords' => [
                ['lat' => -23.5, 'lng' => -46.6],
                ['lat' => -23.5, 'lng' => -46.5],
                ['lat' => -23.4, 'lng' => -46.5],
                ['lat' => -23.4, 'lng' => -46.6],
            ],
            'area_calculada' => $area,
            'area_total' => $area,
            'area_util' => $area * 0.7,
            'area_declividade' => $area * 0.15,
            'area_app' => $area * 0.15,
            'valor' => fake()->randomFloat(2, 100000, 10000000),
            'cep' => fake()->numerify('#####-###'),
            'bairro' => fake()->word(),
            'zona' => fake()->randomElement(['residencial', 'comercial', 'mista', 'industrial']),
            'workflow_stage' => 'captacao',
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
            'workflow_status_changed_at' => now(),
            'qualification_data' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function emAnalise(): static
    {
        return $this->state(fn (): array => [
            'workflow_stage' => 'captacao',
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);
    }

    public function aprovado(): static
    {
        return $this->state(fn (): array => [
            'workflow_stage' => 'viabilidade',
            'workflow_status_code' => WorkflowStatus::VIABILIDADE_APROVADA->value,
        ]);
    }

    public function descartado(): static
    {
        return $this->state(fn (): array => [
            'workflow_stage' => 'encerramento',
            'workflow_status_code' => WorkflowStatus::DESCARTADO->value,
        ]);
    }
}
