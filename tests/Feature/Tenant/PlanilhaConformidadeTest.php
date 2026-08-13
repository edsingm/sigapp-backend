<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeUnificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de cálculo baseado na planilha modelo (v.02.2026).
 *
 * Os valores esperados são os resultados armazenados no XLSX fornecido pelo
 * usuário. Custos da DRE são normalizados como positivos, seguindo a API.
 * As TIRs seguem o XIRR do XLSX sobre os saldos operacionais acumulados.
 */
class PlanilhaConformidadeTest extends TestCase
{
    use RefreshDatabase;

    private ViabilidadeUnificadoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);

        $this->service = app(ViabilidadeUnificadoService::class);
    }

    public function test_compara_dre_fluxo_e_kpis_com_a_planilha_modelo(): void
    {
        $fixture = $this->loadFixture();
        [$terrenoId, $viabilidadeId] = $this->createScenario($fixture['assumptions']);

        $resultado = $this->service->gerarFluxoMensal($terrenoId, $viabilidadeId);
        $dre = $resultado['dre_itens'];
        $indicadores = $resultado['indicadores'];

        $this->assertSame(2000, (int) $resultado['totalUnidades']);
        $this->assertSame(80, (int) $resultado['unidadesPermuta']);
        $this->assertEqualsWithDelta(445_000_000.0, (float) $dre['vgv_total'], 0.01);
        $this->assertEqualsWithDelta(427_500_000.0, (float) $dre['vgv_sem_permutas'], 0.01);
        $this->assertEqualsWithDelta(407_500_000.0, (float) $dre['vgv_sem_terrenista'], 0.01);

        $dreAtual = [
            'receita_total_vendas' => (float) $dre['receita_total_vendas'],
            'juros_correcoes' => (float) $dre['juros_correcoes'],
            'receita_bruta' => (float) $dre['receita_bruta'],
            'pis_cofins' => (float) $dre['pis_cofins_outros'],
            'iss' => (float) $dre['iss'],
            'outras_deducoes' => (float) $dre['outras_deducoes'],
            'receita_liquida' => (float) $dre['receita_liquida'],
            'custo_terreno' => (float) $dre['custo_terreno'],
            'comissao_terreno' => (float) $dre['comissao'],
            'incorporacao' => (float) $dre['incorporacao'],
            'infra_casas' => (float) $dre['infra_casas'],
            'infra_lotes' => (float) $dre['infra_lotes'],
            'area_comum' => (float) $dre['area_comum'],
            'contrapartidas' => (float) $dre['contrapartidas'],
            'canteiro' => (float) $dre['canteiro_total'],
            'mo_administrativa' => (float) $dre['mo_administrativa_total'],
            'seguros' => (float) $dre['seguros'],
            'assistencia_tecnica' => (float) $dre['assistencia_tecnica'],
            'custos_diretos' => (float) $dre['custos_diretos_total'],
            'lucro_bruto' => (float) $dre['lucro_bruto'],
            'despesas_comerciais' => (float) $dre['despesas_comerciais'],
            'marketing' => (float) $dre['marketing'],
            'itbi_iptu' => (float) $dre['itbi_iptu'],
            'registro' => (float) $dre['registro'],
            'medicao_contratacao' => (float) $dre['tx_medicao_contratacao'],
            'contratos_cef' => (float) $dre['contratos_caixa'],
            'produtos_cef' => (float) $dre['produtos_caixa'],
            'despesas_operacionais' => (float) $dre['despesas_operacionais_total'],
            'ebitda' => (float) $dre['ebitda'],
            'outras_despesas_financeiras' => (float) $dre['outras_despesas_financeiras'],
            'juros_pj' => (float) $dre['juros_pj'],
            'ebit' => (float) $dre['ebit'],
            'irpj_csll' => (float) $dre['irpj_csll'],
            'lucro_liquido' => (float) $dre['lucro_liquido_projeto'],
            'margem_sobre_rol_pct' => (float) $dre['indicadores']['margem_liquida_sobre_rol'],
            'margem_sobre_vgv_sem_permuta_pct' => (float) $dre['indicadores']['margem_liquida_sobre_vgv_sem_permuta'],
            'margem_sobre_vgv_sem_terrenista_pct' => (float) $dre['indicadores']['margem_liquida_percentual'],
        ];

        $ultimoFluxo = end($resultado['fluxo_mensal']);
        $ultimoFluxoFinanceiro = end($resultado['fluxo_mensal_financeiro']);
        $fluxoFinanceiro = $resultado['fluxo_mensal_financeiro'];
        $saidasOperacionais = (float) $resultado['totais']['custo_direto']
            + (float) $resultado['totais']['impostos']
            + (float) $resultado['totais']['custos_operacionais']
            + (float) $resultado['totais']['custos_financeiros'];

        $fluxoAtual = [
            'entradas' => (float) $resultado['totais']['receita'],
            'saidas_operacionais' => $saidasOperacionais,
            'fco' => (float) $resultado['totais']['lucro'],
            'saldo_operacional_final' => (float) $ultimoFluxo['saldo_acumulado_mes'],
            'aporte_total' => array_sum(array_column($fluxoFinanceiro, 'aporte')),
            'devolucao_aporte_total' => array_sum(array_column($fluxoFinanceiro, 'devolucao_aporte')),
            'saldo_apos_devolucao_aporte_final' => (float) $ultimoFluxoFinanceiro['saldo_apos_devolucao_aporte'],
            'distribuicao_lucros_total' => array_sum(array_column($fluxoFinanceiro, 'distribuicao_lucros')),
            'saldo_financeiro_final' => (float) $ultimoFluxoFinanceiro['saldo_acumulado'],
            'pj_principal' => (float) $indicadores['divida_pj']['valor_antecipado'],
            'pj_juros' => (float) $indicadores['divida_pj']['juros_totais'],
        ];

        $indicadoresAtuais = [
            'exposicao_operacional' => (float) $indicadores['exposicao_maxima_operacional'],
            'exposicao_financeira' => (float) $indicadores['exposicao_maxima_financeira'],
            'payback_operacional_meses' => $indicadores['payback_operacional_meses'],
            'payback_financeiro_meses' => $indicadores['payback_financeiro_meses'],
            'tir_operacional_aa_pct' => $indicadores['tir_operacional_aa_percentual'],
            'tir_financeira_aa_pct' => $indicadores['tir_financeira_aa_percentual'],
        ];

        $this->assertComparisonBlock(
            'DRE',
            $fixture['expected']['dre'],
            $dreAtual,
            (float) $fixture['tolerances']['dre_pct'],
        );

        $chavesFluxoConformes = [
            'entradas',
            'saidas_operacionais',
            'fco',
            'saldo_operacional_final',
            'aporte_total',
            'devolucao_aporte_total',
            'saldo_apos_devolucao_aporte_final',
            'distribuicao_lucros_total',
            'saldo_financeiro_final',
            'pj_principal',
            'pj_juros',
        ];
        $this->assertComparisonBlock(
            'FLUXO COMPLETO',
            array_intersect_key($fixture['expected']['fluxo'], array_flip($chavesFluxoConformes)),
            array_intersect_key($fluxoAtual, array_flip($chavesFluxoConformes)),
            (float) $fixture['tolerances']['fluxo_pct'],
        );

        $this->logComparison('FLUXO', $fixture['expected']['fluxo'], $fluxoAtual);
        $this->logComparison('KPIs', $fixture['expected']['indicadores'], $indicadoresAtuais);

        $this->assertEqualsWithDelta(
            $fixture['expected']['indicadores']['exposicao_operacional'],
            $indicadoresAtuais['exposicao_operacional'],
            (float) $fixture['tolerances']['money_rounding'],
        );
        $this->assertEqualsWithDelta(
            $fixture['expected']['indicadores']['exposicao_financeira'],
            $indicadoresAtuais['exposicao_financeira'],
            (float) $fixture['tolerances']['money_rounding'],
        );
        $this->assertSame(
            $fixture['expected']['indicadores']['payback_operacional_meses'],
            $indicadoresAtuais['payback_operacional_meses'],
        );
        $this->assertSame(
            $fixture['expected']['indicadores']['payback_financeiro_meses'],
            $indicadoresAtuais['payback_financeiro_meses'],
        );
        $this->assertEqualsWithDelta(
            $fixture['expected']['indicadores']['tir_operacional_aa_pct'],
            $indicadoresAtuais['tir_operacional_aa_pct'],
            (float) $fixture['tolerances']['tir_percentage_points'],
        );
        $this->assertEqualsWithDelta(
            $fixture['expected']['indicadores']['tir_financeira_aa_pct'],
            $indicadoresAtuais['tir_financeira_aa_pct'],
            (float) $fixture['tolerances']['tir_percentage_points'],
        );

        $this->assertSame(
            $fixture['regression_baseline']['fluxo'],
            $this->roundValues($fluxoAtual),
            'O fluxo mudou em relação ao baseline documentado; revise a comparação com a planilha.',
        );
        $this->assertSame(
            $fixture['regression_baseline']['indicadores'],
            $this->roundValues($indicadoresAtuais),
            'Os KPIs mudaram em relação ao baseline documentado; revise a comparação com a planilha.',
        );
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @return array{int, int}
     */
    private function createScenario(array $assumptions): array
    {
        $now = now();
        $curve = json_encode($assumptions['sales_curve'], JSON_THROW_ON_ERROR);

        $terrenoId = DB::table('terrenos')->insertGetId([
            'nome' => 'Lot Flores da Terra',
            'area_calculada' => 0,
            'data_contrato' => '2027-12-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('premissas_viabilidade')->insert([
            'nome' => 'Planilha Modelo v.02.2026',
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            'ativo' => true,
            'versao' => 1,
            'vigente_em' => '2026-01-01',
            'pis_cofins' => 4,
            'iss' => 0,
            'outros_impostos' => 0.5,
            'comissao' => 0,
            'parceria_vgv' => 5,
            'infra_nao_incidente' => 1,
            'incorporacao' => 1,
            'incorp_ri' => 30,
            'incorp_entrega' => 15,
            'incorp_ate_lancamento' => 80,
            'obra_ate_lancamento' => 1,
            'area_comum' => 0,
            'contrapartidas' => 1,
            'canteiro_mensal' => 85_000,
            'mo_administrativa' => 60_000,
            'seguros' => 0.5,
            'assistencia_tecnica' => 1,
            'despesas_comerciais' => 4,
            'stand_vendas' => 200_000,
            'mobilia_decoracao' => 90_000,
            // A planilha e o motor armazenam 0,0001 como razão (0,01%).
            'gastos_mensais_stand' => 0.0001,
            'construcao_stand_meses_antes_lancamento' => 4,
            'comissao_house_percentual' => 3,
            'comissao_imobiliarias_percentual' => 3.5,
            'percentual_vendas_house' => 50,
            'pagamento_comissao_venda' => 50,
            'pagamento_comissao_desligamento' => 50,
            'parcelamento_comissao_meses' => 18,
            'parcelamento_comissao_terreno' => 1,
            'ajuda_custo_gerente' => 5_000,
            'ajuda_custo_gerente_regional' => 2_733,
            'reembolso_logistica' => 5_000,
            'bonus_cca' => 350,
            'bonus_gerente' => 0.3,
            'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05,
            'bonus_gestor_comercial' => 0.05,
            'bonus_equipe_comercial' => -728_286,
            'marketing' => 1,
            'marketing_lancamento' => 25,
            'marketing_inicio_antes_lancamento' => 3,
            'itbi_iptu' => 1.1,
            'registro' => 2_500,
            'custo_contratacao_cef' => 48_000,
            'custo_medicao_cef' => 4_000,
            'contratos_cef' => 300,
            'produtos_cef' => 0.5,
            'outras_despesas_financeiras' => 0.3,
            'despesas_onerosas_bancos' => 10,
            'prazo_obra' => 36,
            'compra_terreno' => 10_000_000,
            'porcentagem_lote_proprietario' => 0,
            'taxa_juros_pj' => 10.5,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'percentual_antecipacao_pj' => 10,
            'aporte_adicional_mensal' => 0,
            'devolucao_aporte_percentual' => 20,
            'distribuicao_lucros_percentual_obra' => 100,
            'taxa_exposicao_aplicada' => 12.5,
            'inadimplencia' => 0,
            'atraso_meses' => 0,
            'taxa_perda' => 0,
            'meses_incorporacao' => 18,
            'meses_lancamento' => 6,
            'meses_entrega' => 1,
            'meses_pos_obra' => 60,
            'variavel_correcao' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId,
            'version' => 1,
            'is_current' => true,
            'prazo_obra' => 36,
            'prazo_incorporacao' => 18,
            'prazo_lancamento' => 6,
            'data_lancamento' => '2029-06-01',
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            'compra_terreno' => 10_000_000,
            'porcentagem_lote_proprietario' => 0,
            'parceria_vgv' => 5,
            'infra_nao_incidente' => 1,
            'pis_cofins' => 4,
            'iss' => 0,
            'outros_impostos' => 0.5,
            'comissao' => 0,
            'incorporacao' => 1,
            'area_comum' => 0,
            'contrapartidas' => 1,
            'canteiro_mensal' => 85_000,
            'mo_administrativa' => 60_000,
            'seguros' => 0.5,
            'assistencia_tecnica' => 1,
            'despesas_comerciais' => 4,
            'stand_vendas' => 200_000,
            'mobilia_decoracao' => 90_000,
            'gastos_mensais_stand' => 0.0001,
            'construcao_stand_meses_antes_lancamento' => 4,
            'comissao_house_percentual' => 3,
            'comissao_imobiliarias_percentual' => 3.5,
            'percentual_vendas_house' => 50,
            'pagamento_comissao_venda' => 50,
            'pagamento_comissao_desligamento' => 50,
            'parcelamento_comissao_meses' => 18,
            'ajuda_custo_gerente' => 5_000,
            'ajuda_custo_gerente_regional' => 2_733,
            'reembolso_logistica' => 5_000,
            'bonus_cca' => 350,
            'bonus_gerente' => 0.3,
            'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05,
            'bonus_gestor_comercial' => 0.05,
            'bonus_equipe_comercial' => -728_286,
            'marketing' => 1,
            'marketing_lancamento' => 25,
            'marketing_inicio_antes_lancamento' => 3,
            'itbi_iptu' => 1.1,
            'registro' => 2_500,
            'custo_contratacao_cef' => 48_000,
            'custo_medicao_cef' => 4_000,
            'contratos_cef' => 300,
            'produtos_cef' => 0.5,
            'outras_despesas_financeiras' => 0.3,
            'percentual_antecipacao_pj' => 10,
            'usar_antecipacao_pj' => true,
            'aporte_adicional_mensal' => 0,
            'devolucao_aporte_percentual' => 20,
            'distribuicao_lucros_percentual_obra' => 100,
            'taxa_exposicao_aplicada' => 12.5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $productIds = [];
        foreach ([
            ['name' => '2 Dorm.', 'private_area' => 47.2, 'units' => 1700, 'price' => 200_000, 'permuta' => 70, 'avaliacao' => 0.20],
            ['name' => '3 Dorm.', 'private_area' => 61.33, 'units' => 300, 'price' => 350_000, 'permuta' => 10, 'avaliacao' => 0.15],
        ] as $product) {
            $productId = DB::table('produtos')->insertGetId([
                'name' => $product['name'],
                'private_area' => $product['private_area'],
                'm2_cost' => 1_400,
                'infra_cost' => 22_000,
                'status' => 'ativo',
                'sinal' => 2,
                'parcela_obra' => 9,
                'parcela_posChave' => 9,
                'qtde_parcelas_posChave' => '36',
                'demanda_minCef' => 30,
                'defasagem_pgtoTerreno' => '1',
                'avaliacao_lotesCef' => $product['avaliacao'],
                'juros_mensalSinal' => 0,
                'juros_mensalObra' => 0,
                'juros_mensalPosChave' => 1,
                'correcao_anualSinal' => 0,
                'correcao_anualObra' => 5,
                'correcao_anualPosChave' => 4.5,
                'curva_vendas' => $curve,
                'assist_tecnica1' => 50,
                'assist_tecnica2' => 20,
                'assist_tecnica3' => 10,
                'assist_tecnica4' => 10,
                'assist_tecnica5' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $productIds[] = $productId;

            DB::table('terreno_produtos')->insert([
                'terreno_id' => $terrenoId,
                'produto_id' => $productId,
                'unidades' => $product['units'],
                'valor' => $product['price'],
                'permuta' => $product['permuta'],
                'pgto_por_lote' => 10_000,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->assertCount(2, $productIds);

        return [$terrenoId, $viabilidadeId];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(): array
    {
        $path = base_path('tests/Fixtures/Viabilidade/planilha_modelo_v02_2026.json');
        $this->assertFileExists($path);

        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($fixture);

        return $fixture;
    }

    /**
     * @param  array<string, float|int>  $expected
     * @param  array<string, float|int|null>  $actual
     */
    private function assertComparisonBlock(string $block, array $expected, array $actual, float $tolerancePct): void
    {
        $failures = [];
        foreach ($expected as $key => $expectedValue) {
            $actualValue = $actual[$key] ?? null;
            if ($actualValue === null) {
                $failures[] = "{$key}: ausente no sistema";

                continue;
            }

            $delta = abs((float) $actualValue - (float) $expectedValue);
            $diffPct = abs((float) $expectedValue) > 0.0001
                ? ($delta / abs((float) $expectedValue)) * 100
                : $delta;
            if ($diffPct > $tolerancePct) {
                $failures[] = sprintf(
                    '%s: planilha=%.4f sistema=%.4f diferença=%.4f%%',
                    $key,
                    $expectedValue,
                    $actualValue,
                    $diffPct,
                );
            }
        }

        $this->logComparison($block, $expected, $actual);
        $this->assertSame([], $failures, $block.' fora da tolerância de '.$tolerancePct."%:\n".implode("\n", $failures));
    }

    /**
     * @param  array<string, float|int|null>  $expected
     * @param  array<string, float|int|null>  $actual
     */
    private function logComparison(string $block, array $expected, array $actual): void
    {
        fwrite(STDOUT, "\n{$block} — PLANILHA x SISTEMA\n");
        foreach ($expected as $key => $expectedValue) {
            $actualValue = $actual[$key] ?? null;
            $diffPct = $actualValue !== null && abs((float) $expectedValue) > 0.0001
                ? (((float) $actualValue - (float) $expectedValue) / abs((float) $expectedValue)) * 100
                : null;
            fwrite(STDOUT, sprintf(
                "%-42s planilha=%16s sistema=%16s diff=%s\n",
                $key,
                is_numeric($expectedValue) ? number_format((float) $expectedValue, 2, ',', '.') : (string) $expectedValue,
                is_numeric($actualValue) ? number_format((float) $actualValue, 2, ',', '.') : 'N/D',
                $diffPct !== null ? number_format($diffPct, 3, ',', '.').'%' : 'N/D',
            ));
        }
    }

    /**
     * @param  array<string, float|int|null>  $values
     * @return array<string, float|int|null>
     */
    private function roundValues(array $values): array
    {
        return array_map(
            static fn (float|int|null $value): float|int|null => is_float($value) ? round($value, 2) : $value,
            $values,
        );
    }
}
