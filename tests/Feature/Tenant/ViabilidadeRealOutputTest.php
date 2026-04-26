<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Viabilidade;
use App\Services\Tenant\Viabilidade\ViabilidadeUnificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ViabilidadeRealOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_real_viability_output_payload(): void
    {
        $this->migrarTabelasTenantViabilidade();

        $agora = now();

        $terrenoId = DB::table('terrenos')->insertGetId([
            'nome' => 'Terreno Teste Viabilidade Real',
            'area_calculada' => 12500.00,
            'data_contrato' => '2026-01-10',
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $produtoId = DB::table('produtos')->insertGetId([
            'name' => 'Casa 2Q 48m2',
            'private_area' => 48.00,
            'm2_cost' => 1650.00,
            'infra_cost' => 22000.00,
            'status' => 'ativo',
            'sinal' => 2.00,
            'parcela_obra' => 10.00,
            'parcela_posChave' => 8.00,
            'qtde_parcelas_posChave' => '36',
            'demanda_minCef' => 30.00,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
            'imposto_tributos' => 4.00,
            'imposto_iss' => 2.00,
            'imposto_outros' => 0.50,
            'curva_vendas' => json_encode([10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1]),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        DB::table('terreno_produtos')->insert([
            'terreno_id' => $terrenoId,
            'produto_id' => $produtoId,
            'unidades' => 120,
            'valor' => 255000.00,
            'permuta' => 8,
            'pgto_por_lote' => 15000.00,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId,
            'prazo_obra' => 24,
            'prazo_lancamento' => 3,
            'prazo_incorporacao' => 6,
            'compra_terreno' => 2500000.00,
            'taxa_juros_pj' => 10.50,
            'percentual_antecipacao_pj' => 50.00,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $service = app(ViabilidadeUnificadoService::class);
        $resultado = $service->gerarFluxoMensal($terrenoId, $viabilidadeId);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('fluxo_mensal', $resultado);
        $this->assertArrayHasKey('dre_itens', $resultado);
        $this->assertArrayHasKey('dre_caixa', $resultado);
        $this->assertArrayHasKey('dre_contabil_poc_mensal_blocos', $resultado);
        $this->assertArrayHasKey('ponte_reconciliacao', $resultado);
        $this->assertNotEmpty($resultado['fluxo_mensal']);

        $primeirosMeses = array_slice($resultado['fluxo_mensal'], 0, 3, true);
        $ultimosMeses = array_slice($resultado['fluxo_mensal'], -3, 3, true);

        $resumoSaida = [
            'terreno_id' => $terrenoId,
            'viabilidade_id' => $viabilidadeId,
            'vgv' => $resultado['vgv'] ?? null,
            'custo_total' => $resultado['custoTotal'] ?? null,
            'dre' => [
                'receita_total_vendas' => $resultado['dre_itens']['receita_total_vendas'] ?? null,
                'receita_liquida' => $resultado['dre_itens']['receita_liquida'] ?? null,
                'custos_diretos_total' => $resultado['dre_itens']['custos_diretos_total'] ?? null,
                'despesas_operacionais_total' => $resultado['dre_itens']['despesas_operacionais_total'] ?? null,
                'ebitda' => $resultado['dre_itens']['ebitda'] ?? null,
                'ebit' => $resultado['dre_itens']['ebit'] ?? null,
                'lucro_liquido_projeto' => $resultado['dre_itens']['lucro_liquido_projeto'] ?? null,
                'margem_liquida_percentual' => $resultado['dre_itens']['indicadores']['margem_liquida_percentual'] ?? null,
            ],
            'dre_contabil_poc' => $resultado['dre_contabil_poc'] ?? [],
            'indicadores_principais' => [
                'tir_operacional' => $resultado['indicadores']['tir_operacional'] ?? null,
                'tir_financeira' => $resultado['indicadores']['tir_financeira'] ?? null,
                'vpl_financeiro' => $resultado['indicadores']['vpl_financeiro'] ?? null,
                'margem_liquida_percentual' => $resultado['indicadores']['margem_liquida_percentual'] ?? null,
                'payback_operacional_meses' => $resultado['indicadores']['payback_operacional_meses'] ?? null,
                'payback_financeiro_meses' => $resultado['indicadores']['payback_financeiro_meses'] ?? null,
            ],
            'vso_janelas' => $resultado['indicadores']['vso_janelas'] ?? [],
            'poc_resumo' => $resultado['dre_contabil_poc_mensal_blocos']['resumo'] ?? [],
            'fluxo_primeiros_3_meses' => $primeirosMeses,
            'fluxo_ultimos_3_meses' => $ultimosMeses,
        ];

        fwrite(
            STDOUT,
            PHP_EOL.'VIABILIDADE_REAL_OUTPUT:'.PHP_EOL.json_encode($resumoSaida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL
        );

        $viabilidade = Viabilidade::query()->find($viabilidadeId);
        $this->assertNotNull($viabilidade);
    }

    public function test_validacao_planilha_modelo_1000_unidades_220k(): void
    {
        $this->migrarTabelasTenantViabilidade();

        $agora = now();

        $terrenoId = DB::table('terrenos')->insertGetId([
            'nome' => 'Planilha Modelo LRG - 1000 unidades 2Dorm R$220k 36m',
            'area_calculada' => 50000.00,
            'data_contrato' => '2026-01-10',
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // Produto: 2 Dorm, 48m2, custo R$1.833/m2 → obra ~R$88M (1000 × 48 × 1833)
        $produtoId = DB::table('produtos')->insertGetId([
            'name' => '2 Dorm 48m2',
            'private_area' => 48.00,
            'm2_cost' => 1833.00,
            'infra_cost' => 0.00,
            'status' => 'ativo',
            'sinal' => 2.00,
            'parcela_obra' => 10.00,
            'parcela_posChave' => 8.00,
            'qtde_parcelas_posChave' => '36',
            'demanda_minCef' => 30.00,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
            'imposto_tributos' => 4.00,
            'imposto_iss' => 2.00,
            'imposto_outros' => 0.50,
            'curva_vendas' => json_encode([10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1]),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // 1000 unidades a R$220k = VGV R$220M
        // Parceria VGV 25% ≈ custo terreno R$55M
        DB::table('terreno_produtos')->insert([
            'terreno_id' => $terrenoId,
            'produto_id' => $produtoId,
            'unidades' => 1000,
            'valor' => 220000.00,
            'permuta' => 0,
            'pgto_por_lote' => 0.00,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId,
            'prazo_obra' => 36,
            'prazo_lancamento' => 6,
            'prazo_incorporacao' => 18,
            'compra_terreno' => 0.00,
            'parceria_vgv' => 25.00, // ~R$55M sobre VGV
            'taxa_juros_pj' => 10.50,
            'percentual_antecipacao_pj' => 10.00,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'pis_cofins' => 4.00,
            'iss' => 2.00,
            'outros_impostos' => 0.50,
            'incorporacao' => 1.00,
            'incorporacao_ri' => 30.00,
            'incorporacao_entrega' => 15.00,
            'incorporacao_ate_lancamento' => 80.00,
            'area_comum' => 0.00,
            'contrapartidas' => 0.00,
            'canteiro_mensal' => 85715.00,
            'mo_administrativa' => 62502.00,
            'seguros' => 0.50,
            'assistencia_tecnica' => 1.00,
            'marketing' => 1.00,
            'marketing_lancamento' => 25.00,
            'produtos_cef' => 0.557,
            'outras_despesas_financeiras' => 0.00,
            'despesas_onerosas_bancos' => 0.00,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $service = app(ViabilidadeUnificadoService::class);
        $resultado = $service->gerarFluxoMensal($terrenoId, $viabilidadeId);

        $dre = $resultado['dre_itens'] ?? [];
        $ind = $resultado['indicadores'] ?? [];

        $saida = [
            '=== PLANILHA MODELO: 1000 unidades 2Dorm R$220k 36m ===' => '',
            'VGV' => number_format($resultado['vgv'] ?? 0, 2, ',', '.'),
            'Receita Total Vendas' => number_format($dre['receita_total_vendas'] ?? 0, 2, ',', '.'),
            'Juros e Correções' => number_format($dre['juros_correcoes'] ?? 0, 2, ',', '.'),
            'Receita Bruta' => number_format($dre['receita_bruta'] ?? 0, 2, ',', '.'),
            'PIS/COFINS' => number_format(($dre['pis_cofins_outros'] ?? 0), 2, ',', '.'),
            'ISS' => number_format(($dre['iss'] ?? 0), 2, ',', '.'),
            'Outras Deduções' => number_format(($dre['outras_deducoes'] ?? 0), 2, ',', '.'),
            'Receita Líquida (ROL)' => number_format($dre['receita_liquida'] ?? 0, 2, ',', '.'),
            '--- CUSTOS DIRETOS ---' => '',
            'Custo Terreno' => number_format($dre['custo_terreno'] ?? 0, 2, ',', '.'),
            'Comissão' => number_format($dre['comissao'] ?? 0, 2, ',', '.'),
            'Incorporação' => number_format($dre['incorporacao'] ?? 0, 2, ',', '.'),
            'Obras (Casas)' => number_format($dre['infra_casas'] ?? 0, 2, ',', '.'),
            'Infra Lotes' => number_format($dre['infra_lotes'] ?? 0, 2, ',', '.'),
            'Área Comum' => number_format($dre['area_comum'] ?? 0, 2, ',', '.'),
            'Canteiro Total' => number_format($dre['canteiro_total'] ?? 0, 2, ',', '.'),
            'M.O. Adm. Total' => number_format($dre['mo_administrativa_total'] ?? 0, 2, ',', '.'),
            'Seguros' => number_format($dre['seguros'] ?? 0, 2, ',', '.'),
            'Assist. Técnica' => number_format($dre['assistencia_tecnica'] ?? 0, 2, ',', '.'),
            'Custos Diretos Total' => number_format($dre['custos_diretos_total'] ?? 0, 2, ',', '.'),
            'Lucro Bruto' => number_format($dre['lucro_bruto'] ?? 0, 2, ',', '.'),
            'Margem Bruta %' => number_format($dre['indicadores']['margem_bruta_percentual'] ?? 0, 2, ',', '.').'%',
            '--- DESPESAS OPERACIONAIS ---' => '',
            'Despesas Comerciais' => number_format($dre['despesas_comerciais'] ?? 0, 2, ',', '.'),
            'Marketing' => number_format($dre['marketing'] ?? 0, 2, ',', '.'),
            'ITBI/IPTU' => number_format($dre['itbi_iptu'] ?? 0, 2, ',', '.'),
            'Outros Operacionais' => number_format($dre['despesas_operacionais_total'] ?? 0, 2, ',', '.'),
            'EBITDA' => number_format($dre['ebitda'] ?? 0, 2, ',', '.'),
            'Margem EBITDA %' => number_format($dre['indicadores']['margem_ebitda_percentual'] ?? 0, 2, ',', '.').'%',
            '--- FINANCEIRO ---' => '',
            'Outras Desp. Financeiras' => number_format($dre['outras_despesas_financeiras'] ?? 0, 2, ',', '.'),
            'Juros PJ' => number_format($dre['juros_pj'] ?? 0, 2, ',', '.'),
            'Desp. Onerosas Bancos' => number_format($dre['despesas_onerosas_bancos'] ?? 0, 2, ',', '.'),
            'EBIT' => number_format($dre['ebit'] ?? 0, 2, ',', '.'),
            'Margem EBIT %' => number_format($dre['indicadores']['margem_ebit_percentual'] ?? 0, 2, ',', '.').'%',
            'IRPJ/CSLL' => number_format($dre['irpj_csll'] ?? 0, 2, ',', '.'),
            'Lucro Líquido' => number_format($dre['lucro_liquido_projeto'] ?? 0, 2, ',', '.'),
            '--- INDICADORES ---' => '',
            'Margem Líquida % (s/VGV)' => number_format($dre['indicadores']['margem_liquida_percentual'] ?? 0, 2, ',', '.').'%',
            'Margem Líquida % (s/ROL)' => number_format($dre['indicadores']['margem_liquida_sobre_rol'] ?? 0, 2, ',', '.').'%',
            'Margem Líquida % (s/VGV s/Permuta)' => number_format($dre['indicadores']['margem_liquida_sobre_vgv_sem_permuta'] ?? 0, 2, ',', '.').'%',
            'ROI %' => number_format($dre['indicadores']['roi_percentual'] ?? 0, 2, ',', '.').'%',
            'TIR Operacional (a.a.)' => number_format(($ind['tir_operacional'] ?? 0) * 100, 2, ',', '.').'%',
            'TIR Financeira (a.a.)' => number_format(($ind['tir_financeira'] ?? 0) * 100, 2, ',', '.').'%',
            'Payback Operacional (meses)' => $ind['payback_operacional_meses'] ?? 'N/A',
            'Payback Financeiro (meses)' => $ind['payback_financeiro_meses'] ?? 'N/A',
            'Exposição Máxima Operacional' => number_format($ind['exposicao_maxima_operacional'] ?? 0, 2, ',', '.'),
            'Exposição Máxima Financeira' => number_format($ind['exposicao_maxima_financeira'] ?? 0, 2, ',', '.'),
            'VSO Total %' => number_format($ind['vso_total_percentual'] ?? 0, 2, ',', '.').'%',
        ];

        fwrite(STDOUT, PHP_EOL);
        foreach ($saida as $label => $valor) {
            if ($valor === '') {
                fwrite(STDOUT, PHP_EOL.$label.PHP_EOL);
            } else {
                fwrite(STDOUT, sprintf("  %-45s R$ %s\n", $label.':', $valor));
            }
        }
        fwrite(STDOUT, PHP_EOL);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('dre_itens', $resultado);
        $this->assertGreaterThan(0, $dre['receita_total_vendas'] ?? 0, 'Receita total deve ser > 0');
    }

    public function test_fluxo_mensal_completo_para_comparacao(): void
    {
        $this->migrarTabelasTenantViabilidade();
        $agora = now();

        $terrenoId = DB::table('terrenos')->insertGetId([
            'nome' => 'Fluxo Comparacao Planilha',
            'area_calculada' => 50000.00,
            'data_contrato' => '2026-01-10',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $produtoId = DB::table('produtos')->insertGetId([
            'name' => '2 Dorm 48m2', 'private_area' => 48.00,
            'm2_cost' => 1833.00, 'infra_cost' => 0.00, 'status' => 'ativo',
            'sinal' => 2.00, 'parcela_obra' => 10.00, 'parcela_posChave' => 8.00,
            'qtde_parcelas_posChave' => '36', 'demanda_minCef' => 30.00,
            'juros_mensalSinal' => 0.00, 'juros_mensalObra' => 0.00, 'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00, 'correcao_anualObra' => 5.00, 'correcao_anualPosChave' => 4.50,
            'imposto_tributos' => 4.00, 'imposto_iss' => 2.00, 'imposto_outros' => 0.50,
            'curva_vendas' => json_encode([10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1]),
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        DB::table('terreno_produtos')->insert([
            'terreno_id' => $terrenoId, 'produto_id' => $produtoId,
            'unidades' => 1000, 'valor' => 220000.00, 'permuta' => 0, 'pgto_por_lote' => 0.00,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId, 'prazo_obra' => 36,
            'prazo_lancamento' => 6, 'prazo_incorporacao' => 18,
            'compra_terreno' => 0.00, 'parceria_vgv' => 25.00,
            'taxa_juros_pj' => 10.50, 'percentual_antecipacao_pj' => 10.00,
            'carencia_pj_meses' => 6, 'amortizacao_pj_parcelas' => 18,
            'pis_cofins' => 4.00, 'iss' => 2.00, 'outros_impostos' => 0.50,
            'incorporacao' => 1.00, 'incorporacao_ri' => 30.00,
            'incorporacao_entrega' => 15.00, 'incorporacao_ate_lancamento' => 80.00,
            'area_comum' => 0.00, 'contrapartidas' => 0.00,
            'canteiro_mensal' => 85715.00, 'mo_administrativa' => 62502.00,
            'seguros' => 0.50, 'assistencia_tecnica' => 1.00,
            'marketing' => 1.00, 'marketing_lancamento' => 25.00,
            'produtos_cef' => 0.557, 'outras_despesas_financeiras' => 0.00,
            'despesas_onerosas_bancos' => 0.00,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $service = app(ViabilidadeUnificadoService::class);
        $resultado = $service->gerarFluxoMensal($terrenoId, $viabilidadeId);

        $fluxo = $resultado['fluxo_mensal'];

        fwrite(STDOUT, PHP_EOL);
        fwrite(STDOUT, str_pad('MÊS', 10).str_pad('PERÍODO', 16).
            str_pad('RECEITA', 18).str_pad('DESPESA', 18).
            str_pad('RESULTADO', 18).str_pad('SALDO ACUM.', 18).
            str_pad('UNID.VEND', 10).PHP_EOL);
        fwrite(STDOUT, str_repeat('-', 108).PHP_EOL);

        $totalReceita = 0;
        $totalDespesa = 0;

        foreach ($fluxo as $mes => $linha) {
            $receita = $linha['receita_total'];
            $despesa = $linha['custos_totais'];
            $resultado_mes = $linha['lucro'];
            $saldo = $linha['saldo_acumulado'];
            $unidades = $linha['unidades_vendidas'];
            $periodo = $linha['periodo'];

            $totalReceita += $receita;
            $totalDespesa += $despesa;

            fwrite(STDOUT,
                str_pad($mes, 10).
                str_pad($periodo, 16).
                str_pad(number_format($receita, 0, ',', '.'), 18, ' ', STR_PAD_LEFT).
                str_pad(number_format($despesa, 0, ',', '.'), 18, ' ', STR_PAD_LEFT).
                str_pad(number_format($resultado_mes, 0, ',', '.'), 18, ' ', STR_PAD_LEFT).
                str_pad(number_format($saldo, 0, ',', '.'), 18, ' ', STR_PAD_LEFT).
                str_pad(number_format($unidades, 1), 10, ' ', STR_PAD_LEFT).
                PHP_EOL
            );

            // Detalhes de receitas e despesas se houver valores
            if ($receita > 0 || $despesa > 0) {
                foreach ($linha['receitas'] as $tipo => $val) {
                    if (abs($val) > 0.01) {
                        fwrite(STDOUT, str_pad('', 26)."  REC: {$tipo}: ".number_format($val, 0, ',', '.').PHP_EOL);
                    }
                }
                foreach ($linha['despesas'] as $tipo => $val) {
                    if (abs($val) > 0.01) {
                        fwrite(STDOUT, str_pad('', 26)."  DES: {$tipo}: ".number_format($val, 0, ',', '.').PHP_EOL);
                    }
                }
            }
        }

        fwrite(STDOUT, str_repeat('-', 108).PHP_EOL);
        fwrite(STDOUT,
            str_pad('TOTAIS', 26).
            str_pad('R$ '.number_format($totalReceita, 0, ',', '.'), 18, ' ', STR_PAD_LEFT).
            str_pad('R$ '.number_format($totalDespesa, 0, ',', '.'), 18, ' ', STR_PAD_LEFT).
            PHP_EOL
        );

        $this->assertIsArray($fluxo);
    }

    private function migrarTabelasTenantViabilidade(): void
    {
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/0001_01_01_000005_create_terrenos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_12_02_184006_create_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_11_13_161116_create_terreno_produto_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_02_07_000000_create_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_03_20_000000_add_viabilidade_campos_planilha.php']);
    }
}
