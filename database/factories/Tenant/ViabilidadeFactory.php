<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<Viabilidade>
 */
class ViabilidadeFactory extends Factory
{
    protected $model = Viabilidade::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'version' => 1,
            'is_current' => true,
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            'parceria_vgv' => fake()->randomFloat(2, 0, 1000000),
            'compra_terreno' => fake()->randomFloat(2, 100000, 5000000),
            'pis_cofins' => 4.00,
            'iss' => fake()->randomFloat(2, 0, 5),
            'outros_impostos' => 0.50,
            'comissao' => fake()->randomFloat(2, 4, 8),
            'prazo_obra' => fake()->numberBetween(18, 48),
            'prazo_lancamento' => fake()->numberBetween(6, 18),
            'prazo_incorporacao' => fake()->numberBetween(12, 36),
            'data_lancamento' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'status' => 'rascunho',
            'approval_status' => 'pendente',
            'resultados_dre' => null,
            'premissas_snapshot' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function atual(): static
    {
        return $this->state(fn (): array => [
            'is_current' => true,
        ]);
    }

    public function rascunho(): static
    {
        return $this->state(fn (): array => [
            'is_current' => false,
            'status' => 'rascunho',
        ]);
    }

    public function aprovada(): static
    {
        return $this->state(fn (): array => [
            'approval_status' => 'aprovada',
            'approval_decided_at' => now(),
        ]);
    }

    public function rejeitada(): static
    {
        return $this->state(fn (): array => [
            'approval_status' => 'rejeitada',
            'approval_decided_at' => now(),
        ]);
    }
}
