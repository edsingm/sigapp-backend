<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Models\Tenant\PremissasViabilidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<PremissasViabilidade>
 */
class PremissasViabilidadeFactory extends Factory
{
    protected $model = PremissasViabilidade::class;

    public function definition(): array
    {
        return [
            'nome' => 'Premissas '.fake()->word(),
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            'ativo' => true,
            'versao' => 1,
            'vigente_em' => now()->subDays(30)->format('Y-m-d'),
            'encerrada_em' => null,
            'pis_cofins' => 4.00,
            'iss' => 0.00,
            'outros_impostos' => 0.50,
            'comissao' => 6.00,
            'parceria_vgv' => 0.00,
            'incorporacao' => 1.00,
            'area_comum' => 2000.00,
            'contrapartidas' => 1.00,
            'canteiro_mensal' => 75000.00,
            'mo_administrativa' => 82000.00,
            'seguros' => 0.50,
            'assistencia_tecnica' => 1.00,
            'despesas_comerciais' => 5.00,
            'marketing' => 1.00,
            'itbi_iptu' => 1.10,
            'registro' => 2500.00,
            'compra_terreno' => 0.00,
            'prazo_obra' => 36,
            'taxa_juros_pj' => 1.00,
            'inadimplencia' => 5.00,
            'taxa_perda' => 2.00,
            'meses_incorporacao' => 12,
            'meses_lancamento' => 6,
            'meses_entrega' => 36,
            'meses_pos_obra' => 12,
        ];
    }

    public function ativa(): static
    {
        return $this->state(fn (): array => [
            'ativo' => true,
            'encerrada_em' => null,
        ]);
    }

    public function inativa(): static
    {
        return $this->state(fn (): array => [
            'ativo' => false,
            'encerrada_em' => now()->format('Y-m-d'),
        ]);
    }

    public function cef(): static
    {
        return $this->state(fn (): array => [
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
        ]);
    }

    public function proprio(): static
    {
        return $this->state(fn (): array => [
            'perfil_financiamento' => PerfilFinanciamento::PROPRIO->value,
        ]);
    }
}
