<?php

namespace Tests\Feature\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Models\Tenant\PremissasViabilidade;
use Database\Seeders\Tenant\PremissasViabilidadeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremissasViabilidadeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
    }

    public function test_seeds_cef_premises_from_planilha_modelo(): void
    {
        $this->seed(PremissasViabilidadeSeeder::class);

        $premissa = PremissasViabilidade::query()
            ->where('perfil_financiamento', PerfilFinanciamento::CEF->value)
            ->firstOrFail();

        $expectedFloats = [
            'pis_cofins' => 4.0,
            'iss' => 0.0,
            'outros_impostos' => 0.5,
            'comissao' => 0.0,
            'parceria_vgv' => 5.0,
            'infra_nao_incidente' => 1.0,
            'incorporacao' => 1.0,
            'incorp_ri' => 30.0,
            'incorp_entrega' => 15.0,
            'incorp_ate_lancamento' => 80.0,
            'obra_ate_lancamento' => 1.0,
            'area_comum' => 0.0,
            'contrapartidas' => 1.0,
            'canteiro_mensal' => 85_000.0,
            'mo_administrativa' => 60_000.0,
            'seguros' => 0.5,
            'assistencia_tecnica' => 1.0,
            'despesas_comerciais' => 4.0,
            'stand_vendas' => 200_000.0,
            'mobilia_decoracao' => 90_000.0,
            'gastos_mensais_stand' => 0.0001,
            'comissao_house_percentual' => 3.0,
            'comissao_imobiliarias_percentual' => 3.5,
            'percentual_vendas_house' => 50.0,
            'ajuda_custo_gerente' => 5_000.0,
            'ajuda_custo_gerente_regional' => 2_733.0,
            'reembolso_logistica' => 5_000.0,
            'bonus_cca' => 350.0,
            'bonus_gerente' => 0.3,
            'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05,
            'bonus_gestor_comercial' => 0.05,
            'bonus_equipe_comercial' => -728_286.0,
            'pagamento_comissao_venda' => 50.0,
            'pagamento_comissao_desligamento' => 50.0,
            'marketing' => 1.0,
            'marketing_lancamento' => 25.0,
            'itbi_iptu' => 1.1,
            'registro' => 2_500.0,
            'custo_contratacao_cef' => 48_000.0,
            'custo_medicao_cef' => 4_000.0,
            'contratos_cef' => 300.0,
            'produtos_cef' => 0.5,
            'outras_despesas_financeiras' => 0.3,
            'despesas_onerosas_bancos' => 10.0,
            'compra_terreno' => 10_000_000.0,
            'porcentagem_lote_proprietario' => 0.0,
            'taxa_juros_pj' => 10.5,
            'percentual_antecipacao_pj' => 10.0,
            'aporte_adicional_mensal' => 0.0,
            'devolucao_aporte_percentual' => 20.0,
            'distribuicao_lucros_percentual_obra' => 100.0,
            'taxa_exposicao_aplicada' => 12.5,
            'inadimplencia' => 0.0,
            'taxa_perda' => 0.0,
            'variavel_correcao' => 0.0,
        ];

        foreach ($expectedFloats as $field => $expected) {
            $this->assertSame($expected, (float) $premissa->getAttribute($field), $field);
        }

        $expectedIntegers = [
            'construcao_stand_meses_antes_lancamento' => 4,
            'parcelamento_comissao_meses' => 18,
            'parcelamento_comissao_terreno' => 1,
            'marketing_inicio_antes_lancamento' => 3,
            'prazo_obra' => 36,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'atraso_meses' => 0,
            'meses_incorporacao' => 18,
            'meses_lancamento' => 6,
            'meses_entrega' => 1,
            'meses_pos_obra' => 60,
        ];

        foreach ($expectedIntegers as $field => $expected) {
            $this->assertSame($expected, (int) $premissa->getAttribute($field), $field);
        }
    }

    public function test_keeps_own_profile_defaults_independent_from_cef_workbook(): void
    {
        $this->seed(PremissasViabilidadeSeeder::class);

        $premissa = PremissasViabilidade::query()
            ->where('perfil_financiamento', PerfilFinanciamento::PROPRIO->value)
            ->firstOrFail();

        $this->assertSame(0.0, (float) $premissa->parceria_vgv);
        $this->assertSame(0.0, (float) $premissa->contrapartidas);
        $this->assertSame(5.0, (float) $premissa->despesas_comerciais);
        $this->assertSame(0.0, (float) $premissa->stand_vendas);
        $this->assertSame(0.0, (float) $premissa->compra_terreno);
        $this->assertSame(0.15, (float) $premissa->inadimplencia);
        $this->assertSame(3, (int) $premissa->atraso_meses);
        $this->assertSame(0.05, (float) $premissa->taxa_perda);
    }

    public function test_seeds_canonical_financing_profiles_without_overwriting_existing_premises(): void
    {
        $this->seed(PremissasViabilidadeSeeder::class);

        $perfis = PremissasViabilidade::query()
            ->orderBy('perfil_financiamento')
            ->pluck('perfil_financiamento')
            ->map(static fn (PerfilFinanciamento|string $perfil): string => $perfil instanceof PerfilFinanciamento
                ? $perfil->value
                : $perfil)
            ->all();

        $this->assertEqualsCanonicalizing(PerfilFinanciamento::values(), $perfis);

        $planoEmpresario = PremissasViabilidade::query()
            ->where('perfil_financiamento', PerfilFinanciamento::PLANO_EMPRESARIO->value)
            ->firstOrFail();
        $alocacaoRecursos = PremissasViabilidade::query()
            ->where('perfil_financiamento', PerfilFinanciamento::ALOCACAO_RECURSOS->value)
            ->firstOrFail();

        $this->assertSame(80.0, (float) $planoEmpresario->percentual_antecipacao_pj);
        $this->assertSame(0.0, (float) $alocacaoRecursos->percentual_antecipacao_pj);

        $planoEmpresario->update(['percentual_antecipacao_pj' => 72.0]);
        $this->seed(PremissasViabilidadeSeeder::class);

        $planoEmpresarioAtualizado = PremissasViabilidade::query()
            ->where('perfil_financiamento', PerfilFinanciamento::PLANO_EMPRESARIO->value)
            ->firstOrFail();

        $this->assertSame(72.0, (float) $planoEmpresarioAtualizado->percentual_antecipacao_pj);
        $this->assertSame(5, PremissasViabilidade::query()->count());
    }
}
