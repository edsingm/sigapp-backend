<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<LegalizacaoEtapa>
 */
class LegalizacaoEtapaFactory extends Factory
{
    protected $model = LegalizacaoEtapa::class;

    public function definition(): array
    {
        $custos = [
            [
                'tipo_custo' => fake()->randomElement(['taxa', 'documentacao', 'cartorio', 'consultoria']),
                'valor_custo' => fake()->randomFloat(2, 0, 25000),
                'custo_pago' => fake()->boolean(),
            ],
            [
                'tipo_custo' => fake()->randomElement(['vistoria', 'licenca', 'honorarios']),
                'valor_custo' => fake()->randomFloat(2, 0, 25000),
                'custo_pago' => fake()->boolean(),
            ],
        ];
        $valorTotal = array_sum(array_map(fn ($custo) => (float) $custo['valor_custo'], $custos));
        $todosPagos = collect($custos)->every(fn ($custo) => (bool) $custo['custo_pago']);

        return [
            'legalizacao_id' => Legalizacao::factory(),
            'titulo' => fake()->sentence(2),
            'descricao' => fake()->paragraph(),
            'ordem' => fake()->numberBetween(1, 10),
            'status' => fake()->randomElement(['pendente', 'em_andamento', 'concluida', 'atrasada', 'bloqueada']),
            'inicio_planejado' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'fim_planejado' => fake()->dateTimeBetween('+2 months', '+3 months'),
            'percentual' => fake()->numberBetween(0, 100),
            'responsavel_id' => User::factory(),
            'cor' => fake()->hexColor(),
            'tipo_custo' => 'Diversos',
            'valor_custo' => $valorTotal,
            'custo_pago' => $todosPagos,
            'custos' => $custos,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function concluida(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'concluida',
            'percentual' => 100,
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
            'status' => 'atrasada',
        ]);
    }
}
