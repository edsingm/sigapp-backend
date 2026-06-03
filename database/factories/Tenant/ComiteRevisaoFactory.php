<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<ComiteRevisao>
 */
class ComiteRevisaoFactory extends Factory
{
    protected $model = ComiteRevisao::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'viabilidade_id' => Viabilidade::factory(),
            'status' => 'em_analise',
            'final_decision' => null,
            'final_comments' => null,
            'required_departments' => ['comercial', 'juridico', 'tecnico'],
            'decided_by' => null,
            'decided_at' => null,
        ];
    }

    public function pendente(): static
    {
        return $this->state(fn (): array => [
            'status' => 'em_analise',
            'final_decision' => null,
        ]);
    }

    public function aprovado(): static
    {
        return $this->state(fn (): array => [
            'status' => 'concluido',
            'final_decision' => 'aprovado',
            'final_comments' => fake()->sentence(),
            'decided_at' => now(),
        ]);
    }

    public function aprovadoComRessalvas(): static
    {
        return $this->state(fn (): array => [
            'status' => 'concluido',
            'final_decision' => 'aprovado_com_ressalvas',
            'final_comments' => fake()->sentence(),
            'decided_at' => now(),
        ]);
    }

    public function rejeitado(): static
    {
        return $this->state(fn (): array => [
            'status' => 'concluido',
            'final_decision' => 'rejeitado',
            'final_comments' => fake()->sentence(),
            'decided_at' => now(),
        ]);
    }
}
