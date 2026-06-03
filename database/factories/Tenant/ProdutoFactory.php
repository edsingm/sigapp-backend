<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<Produto>
 */
class ProdutoFactory extends Factory
{
    protected $model = Produto::class;

    public function definition(): array
    {
        return [
            'name' => 'Produto '.fake()->word(),
            'description' => fake()->optional(0.6)->paragraph(),
            'image' => null,
            'private_area' => fake()->randomFloat(2, 50, 300),
            'm2_cost' => fake()->randomFloat(2, 1500, 5000),
            'infra_cost' => fake()->randomFloat(2, 500, 2000),
            'status' => 'ativo',
            'sinal' => fake()->randomFloat(2, 1000, 20000),
            'parcela_obra' => fake()->randomFloat(2, 500, 5000),
            'parcela_posChave' => fake()->randomFloat(2, 500, 3000),
            'qtde_parcelas_posChave' => fake()->numberBetween(12, 60),
            'juros_mensalSinal' => fake()->randomFloat(4, 0, 2),
            'juros_mensalObra' => fake()->randomFloat(4, 0, 2),
            'juros_mensalPosChave' => fake()->randomFloat(4, 0, 2),
            'correcao_anualSinal' => fake()->randomFloat(4, 0, 12),
            'correcao_anualObra' => fake()->randomFloat(4, 0, 12),
            'correcao_anualPosChave' => fake()->randomFloat(4, 0, 12),
            'curva_vendas' => null,
            'baloes_anuais' => null,
            'meses_inicioConstrucao' => fake()->numberBetween(3, 12),
            'porcentagem_ConstrucaoStand' => fake()->randomFloat(2, 0, 100),
        ];
    }

    public function ativo(): static
    {
        return $this->state(fn (): array => [
            'status' => 'ativo',
        ]);
    }

    public function inativo(): static
    {
        return $this->state(fn (): array => [
            'status' => 'inativo',
        ]);
    }
}
