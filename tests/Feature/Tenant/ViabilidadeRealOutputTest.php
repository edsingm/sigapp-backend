<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Viabilidade;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeUnificadoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
            'private_area' => 47.2,
            'm2_cost' => 1400.00,
            'infra_cost' => 22000.00,
            'status' => 'ativo',
            'sinal' => 2.00,
            'parcela_obra' => 9.00,
            'parcela_posChave' => 9.00,
            'qtde_parcelas_posChave' => '36',
            'demanda_minCef' => 30.00,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
            'curva_vendas' => json_encode([10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1]),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        DB::table('terreno_produtos')->insert([
            'terreno_id' => $terrenoId,
            'produto_id' => $produtoId,
            'unidades' => 1000,
            'valor' => 220000.00,
            'permuta' => 80,
            'pgto_por_lote' => 10000.00,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId,
            'prazo_obra' => 36,
            'prazo_lancamento' => 6,
            'prazo_incorporacao' => 18,
            'compra_terreno' => 10000000.00,
            'taxa_juros_pj' => 10.50,
            'percentual_antecipacao_pj' => 10.00,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $service = app(ViabilidadeUnificadoService::class);
        $resultado = $service->gerarFluxoMensal($terrenoId, $viabilidadeId);

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
            'name' => '2 Dorm 47.2m2',
            'private_area' => 47.2,
            'm2_cost' => 1400.00,
            'infra_cost' => 0.00,
            'status' => 'ativo',
            'sinal' => 2.00,
            'parcela_obra' => 9.00,
            'parcela_posChave' => 9.00,
            'qtde_parcelas_posChave' => '36',
            'demanda_minCef' => 30.00,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
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
            'permuta' => 80,
            'pgto_por_lote' => 10000.00,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId,
            'prazo_obra' => 36,
            'prazo_lancamento' => 6,
            'prazo_incorporacao' => 18,
            'compra_terreno' => 0.00,
            'parceria_vgv' => 8.00, // ~R$55M sobre VGV
            'taxa_juros_pj' => 10.50,
            'percentual_antecipacao_pj' => 10.00,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'data_lancamento' => '2029-06-01',
            'pis_cofins' => 4.00,
            'iss' => 0.00,
            'outros_impostos' => 0.50,
            'incorporacao' => 1.00,
            'incorporacao_ri' => 30.00,
            'incorporacao_entrega' => 15.00,
            'incorporacao_ate_lancamento' => 80.00,
            'area_comum' => 0.00,
            'contrapartidas' => 1.00,
            'canteiro_mensal' => 85715.00,
            'mo_administrativa' => 62502.00,
            'seguros' => 0.50,
            'assistencia_tecnica' => 1.00,
            'marketing' => 1.00,
            'marketing_lancamento' => 25.00,
            'produtos_cef' => 0.006, // 0.6% do VGV (rateio: 1035.20/220000 = 0.47%, verificando)
            'contratos_cef' => 300.00, // valor fixo por unidade (planilha)
            'outras_despesas_financeiras' => 0.003,
            'despesas_onerosas_bancos' => 10.00,
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

        $arquivoExcel = $this->exportarResultadoParaExcel(
            $resultado,
            'planilha-modelo-1000-unidades-220k-36m',
            $terrenoId,
            $viabilidadeId
        );
        fwrite(STDOUT, 'Planilha Excel gerada em: '.$arquivoExcel.PHP_EOL.PHP_EOL);

        $this->assertArrayHasKey('dre_itens', $resultado);
        $this->assertGreaterThan(0, $dre['receita_total_vendas'] ?? 0, 'Receita total deve ser > 0');
        $this->assertFileExists($arquivoExcel);
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
            'm2_cost' => 1400.00, 'infra_cost' => 22000.00, 'status' => 'ativo',
            'sinal' => 2.00, 'parcela_obra' => .00, 'parcela_posChave' => 9.00,
            'qtde_parcelas_posChave' => '36', 'demanda_minCef' => 30.00,
            'juros_mensalSinal' => 0.00, 'juros_mensalObra' => 0.00, 'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00, 'correcao_anualObra' => 5.00, 'correcao_anualPosChave' => 4.50,
            'curva_vendas' => json_encode([10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1]),
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        DB::table('terreno_produtos')->insert([
            'terreno_id' => $terrenoId, 'produto_id' => $produtoId,
            'unidades' => 1000, 'valor' => 220000.00, 'permuta' => 0, 'pgto_por_lote' => 10000.00,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId, 'prazo_obra' => 36,
            'prazo_lancamento' => 6, 'prazo_incorporacao' => 18,
            'compra_terreno' => 10000000.00, 'parceria_vgv' => 8.00,
            'taxa_juros_pj' => 10.50, 'percentual_antecipacao_pj' => 10.00,
            'carencia_pj_meses' => 6, 'amortizacao_pj_parcelas' => 18,
            'data_lancamento' => '2029-06-01',
            'pis_cofins' => 4.00, 'iss' => 0.00, 'outros_impostos' => 0.50,
            'incorporacao' => 1.00, 'incorporacao_ri' => 30.00,
            'incorporacao_entrega' => 15.00, 'incorporacao_ate_lancamento' => 80.00,
            'area_comum' => 0.00, 'contrapartidas' => 1.00,
            'canteiro_mensal' => 85715.00, 'mo_administrativa' => 62502.00,
            'seguros' => 0.50, 'assistencia_tecnica' => 1.00,
            'marketing' => 1.00, 'marketing_lancamento' => 25.00,
            'produtos_cef' => 0.006, // 0.6% do VGV
            'contratos_cef' => 300.00, // valor fixo por unidade (planilha)
            'outras_despesas_financeiras' => 0.003,
            'despesas_onerosas_bancos' => 10.00,
            'itbi_iptu' => 1.10, // 1.1% total (0.8% ITBI + 0.3% IPTU)
            'registro' => 2500.00, // valor fixo por unidade (planilha: 2086.78 por unid)
            'custo_contratacao_cef' => 48000.00, // fixo no 1o mes de lancamento
            'custo_medicao_cef' => 4000.00, // fixo mensal durante obra
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
            $receita = $linha['receitas']['total'];
            $despesa = $linha['despesas']['total'];
            $resultado_mes = $linha['saldo_mes'];
            $saldo = $linha['saldo_acumulado_mes'];
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
                $this->logDetalhesNested($linha['receitas'], 'REC');
                $this->logDetalhesNested($linha['despesas'], 'DES');
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

    /**
     * Teste que replica EXATAMENTE os parâmetros da planilha modelo LRG.
     *
     * Premissas da planilha:
     *   - 2 Dorm: 1000 unid (920 LRG), R$220k, 47.2m², sinal 2%, obra 9%, pós 9%
     *   - 3 Dorm: 100 unid (90 LRG), R$250k, 61.33m², sinal 2%, obra 9%, pós 9%
     *   - Lotes: 200 unid (200 LRG), R$120k, sinal 10%, obra 10%, pós 80%
     *   - Incorporação: 18m, Lançamento: 6m, Obra: 36m, Pós-Obra: 60m
     *   - Data lançamento: 2029-06-01
     *   - VGV LRG s/ Terrenista: R$236.900.000
     *   - Permuta financeira: 8% VGV, Permuta física: 90 unid
     */
    public function test_comparacao_completa_planilha_lrg(): void
    {
        $this->migrarTabelasTenantViabilidade();
        $agora = now();

        $terrenoId = DB::table('terrenos')->insertGetId([
            'nome' => 'Planilha LRG Completa - 2Dorm+3Dorm+Lotes',
            'area_calculada' => 53333.00,
            'data_contrato' => '2026-01-10',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        // Produto 1: 2 Dorm - 1000 unid (920 LRG), R$220k, 47.2m²
        // DRE: m2_cost=1400, infra_cost=22000, area_comum=1500/unid, contrapartidas=1%
        $prod2dormId = DB::table('produtos')->insertGetId([
            'name' => '2 Dorm 47m2',
            'private_area' => 47.20,
            'm2_cost' => 1400.00,
            'infra_cost' => 22000.00,
            'status' => 'ativo',
            'sinal' => 2.00,
            'parcela_obra' => 9.00,
            'parcela_posChave' => 9.00,
            'qtde_parcelas_posChave' => '36',
            'demanda_minCef' => 30.00,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
            'curva_vendas' => json_encode([
                10.0, 9.0, 8.1, 7.29, 6.561, 5.9049, 5.31441,
                3.416406428571428, 3.416406428571428, 3.416406428571428,
                3.416406428571428, 3.416406428571428, 3.416406428571428,
                3.416406428571428, 3.416406428571428,
            ]),
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        DB::table('terreno_produtos')->insert([
            'terreno_id' => $terrenoId, 'produto_id' => $prod2dormId,
            'unidades' => 1000, 'valor' => 220000.00, 'permuta' => 80,
            'pgto_por_lote' => 10000.00,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        // Viabilidade com parâmetros EXATOS da planilha Premissas + DRE
        // DRE formulas: Terreno=R60, Casas=R65, Lotes=R66, AreaComum=R67, Contrap=R68
        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId,
            'prazo_obra' => 36,
            'prazo_lancamento' => 6,
            'prazo_incorporacao' => 18,
            'compra_terreno' => 10000000.00,  // DRE R28 = R$10M fixo
            'parceria_vgv' => 8.00,            // DRE R26 = 8%
            'infra_nao_incidente' => 1.00,     // DRE R37 = 1% do VGV
            'taxa_juros_pj' => 10.50,
            'percentual_antecipacao_pj' => 10.00,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'data_lancamento' => '2029-06-01',
            'pis_cofins' => 4.00,
            'iss' => 0.00,
            'outros_impostos' => 0.50,
            'incorporacao' => 1.00,
            'incorporacao_ri' => 0.00,         // RI já incluso no incorporacao total
            'incorporacao_entrega' => 0.00,
            'incorporacao_ate_lancamento' => 80.00,
            'area_comum' => 1500.00,            // DRE D67 = R$1.500/unidade
            'contrapartidas' => 1.00,           // DRE D68 = 1% do VGV
            'canteiro_mensal' => 85715.00,      // DRE D69
            'mo_administrativa' => 62502.00,    // DRE D70
            'seguros' => 0.50,                  // DRE D72 = 0.5% do VGV
            'assistencia_tecnica' => 1.00,      // DRE D73 = 1% do custo obra selecionado
            'marketing' => 1.00,                // DRE D79 = 1% do VGV LRG
            'marketing_lancamento' => 25.00,
            'despesas_comerciais' => 5.00,       // DRE D78 = 5% do VGV LRG
            'comissao' => 0.00,                  // Comissão do terreno é 1% separado
            'itbi_iptu' => 1.10,                // 1.1% do VGV sem permuta = R$2.420/2Dorm, R$2.750/3Dorm
            'registro' => 2500.00,              // R$2.500/unidade (planilha: 2086.78 total rateado)
            'produtos_cef' => 0.535,            // 0.535% do VGV base = R$1.252,6k (planilha)
            'contratos_cef' => 300.00,          // R$300/unidade (planilha: 250.41 total rateado)
            'medicao_contratacao' => 190.10,    // Tx Med+Contrat por unidade = R$192k total
            'outras_despesas_financeiras' => 0.30,
            'despesas_onerosas_bancos' => 0.00,
            'stand_vendas' => 290000.00,
            'mobilia_decoracao' => 90000.00,
            'ajuda_custo_gerente' => 5000.00,
            'ajuda_custo_gerente_regional' => 2733.00,
            'reembolso_logistica' => 5000.00,
            'bonus_cca' => 350.00,
            'bonus_gerente' => 0.30,
            'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05,
            'bonus_gestor_comercial' => 0.05,
            'pagamento_comissao_desligamento' => 50.00,
            'parcelamento_comissao_meses' => 18,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $service = app(ViabilidadeUnificadoService::class);
        $resultado = $service->gerarFluxoMensal($terrenoId, $viabilidadeId);

        $dre = $resultado['dre_itens'] ?? [];
        $ind = $resultado['indicadores'] ?? [];
        $fluxo = $resultado['fluxo_mensal'];

        // ============================================================
        // COMPARAÇÃO COM VALORES DA PLANILHA (R$ mil) — 1 produto: 2Dorm
        // ============================================================
        // 1000 unid (920 LRG), R$220k, VGV LRG s/Terrenista = R$192.4M
        $planilha = [
            'Receita Total Vendas (VGV)' => 192400.0,
            'Juros + Correções' => 4752.9,
            'Receita Bruta' => 197152.9,
            'PIS/COFINS (s/Receita Bruta)' => 4689.0,
            'Outras Deduções + ISS' => 1100.0,
            'Receita Líquida (ROL)' => 191363.9,
            'Custo Terreno' => 35263.1,
            'Incorporação' => 2200.0,
            'Obra (Casas+Infra+Cant+AC+Contrap)' => 90019.3,
            'MO Adm + Seguros + Assist Tec' => 4219.4,
            'Custos Diretos Total' => 131701.8,
            'Lucro Bruto' => 59662.0,
            'Despesas Comerciais' => 10120.0,
            'Marketing' => 2024.0,
            'ITBI/IPTU + Registro' => 4526.4,
            'Tx Med+Contratos+Produtos Cx' => 1465.0,
            'Despesas Operacionais Total' => 18135.4,
            'EBITDA' => 41526.7,
            'Outras Desp Financeiras' => 0.0,
            'Juros PJ' => 3873.4,
            'EBIT' => 37653.2,
            'IRPJ/CSLL' => 4328.3,
            'Lucro Líquido (DRE)' => 33324.9,
            'Margem Líquida % (s/VGV)' => 17.32,
            'Margem Líquida % (s/ROL)' => 17.41,
        ];

        // Converter sistema de R$ para R$ mil (valores positivos = sistema armazena absoluto)
        $sistema = [
            'Receita Total Vendas (VGV)' => round(($dre['receita_total_vendas'] ?? 0) / 1000, 1),
            'Juros + Correções' => round(($dre['juros_correcoes'] ?? 0) / 1000, 1),
            'Receita Bruta' => round(($dre['receita_bruta'] ?? 0) / 1000, 1),
            'PIS/COFINS (s/Receita Bruta)' => round(($dre['pis_cofins_outros'] ?? 0) / 1000, 1),
            'Outras Deduções + ISS' => round((($dre['iss'] ?? 0) + ($dre['outras_deducoes'] ?? 0)) / 1000, 1),
            'Receita Líquida (ROL)' => round(($dre['receita_liquida'] ?? 0) / 1000, 1),
            'Custo Terreno' => round(($dre['custo_terreno'] ?? 0) / 1000, 1),
            'Incorporação' => round(($dre['incorporacao'] ?? 0) / 1000, 1),
            'Obra (Casas+Infra+Cant+AC+Contrap)' => round((($dre['infra_casas'] ?? 0) + ($dre['infra_lotes'] ?? 0) + ($dre['area_comum'] ?? 0) + ($dre['canteiro_total'] ?? 0) + ($dre['contrapartidas'] ?? 0)) / 1000, 1),
            'MO Adm + Seguros + Assist Tec' => round((($dre['mo_administrativa_total'] ?? 0) + ($dre['seguros'] ?? 0) + ($dre['assistencia_tecnica'] ?? 0)) / 1000, 1),
            'Custos Diretos Total' => round(($dre['custos_diretos_total'] ?? 0) / 1000, 1),
            'Lucro Bruto' => round(($dre['lucro_bruto'] ?? 0) / 1000, 1),
            'Despesas Comerciais' => round(($dre['despesas_comerciais'] ?? 0) / 1000, 1),
            'Marketing' => round(($dre['marketing'] ?? 0) / 1000, 1),
            'ITBI/IPTU + Registro' => round((($dre['itbi_iptu'] ?? 0) + ($dre['registro'] ?? 0)) / 1000, 1),
            'Tx Med+Contratos+Produtos Cx' => round((($dre['tx_medicao_contratacao'] ?? 0) + ($dre['contratos_caixa'] ?? 0) + ($dre['produtos_caixa'] ?? 0)) / 1000, 1),
             'Despesas Operacionais Total' => round(($dre['despesas_operacionais_total'] ?? 0) / 1000, 1),
            'EBITDA' => round(($dre['ebitda'] ?? 0) / 1000, 1),
            'Outras Desp Financeiras' => round(($dre['outras_despesas_financeiras'] ?? 0) / 1000, 1),
            'Juros PJ' => round(($dre['juros_pj'] ?? 0) / 1000, 1),
            'EBIT' => round(($dre['ebit'] ?? 0) / 1000, 1),
            'IRPJ/CSLL' => round(($dre['irpj_csll'] ?? 0) / 1000, 1),
            'Lucro Líquido (DRE)' => round(($dre['lucro_liquido_projeto'] ?? 0) / 1000, 1),
            'Margem Líquida % (s/VGV)' => round($dre['indicadores']['margem_liquida_percentual'] ?? 0, 2),
            'Margem Líquida % (s/ROL)' => round($dre['indicadores']['margem_liquida_sobre_rol'] ?? 0, 2),
        ];

        fwrite(STDOUT, PHP_EOL);
        fwrite(STDOUT, str_repeat('=', 110).PHP_EOL);
        fwrite(STDOUT, 'COMPARAÇÃO: PLANILHA (R$ mil) vs SISTEMA (R$ mil)'.PHP_EOL);
        fwrite(STDOUT, str_repeat('=', 110).PHP_EOL);
        fwrite(STDOUT, PHP_EOL);
        fwrite(STDOUT, sprintf("%-45s %15s %15s %15s\n", 'Indicador', 'Planilha', 'Sistema', 'Diferença'));
        fwrite(STDOUT, str_repeat('-', 90).PHP_EOL);

        foreach ($planilha as $key => $valPlanilha) {
            $valSistema = $sistema[$key];
            $diff = $valPlanilha - $valSistema;
            $pct = $valPlanilha != 0 ? round(($diff / abs($valPlanilha)) * 100, 1) : 0;
            fwrite(STDOUT, sprintf("%-45s %15.1f %15.1f %15.1f (%5.1f%%)\n", $key, $valPlanilha, $valSistema, $diff, $pct));
        }

        // TOTAIS agregados do fluxo mensal para validação
        $totalReceita = 0;
        $totalDespesa = 0;
        foreach ($fluxo as $linha) {
            $totalReceita += $linha['receitas']['total'];
            $totalDespesa += $linha['despesas']['total'];
        }

        fwrite(STDOUT, PHP_EOL);
        fwrite(STDOUT, '--- TOTAIS DO FLUXO MENSAL (R$) ---'.PHP_EOL);
        fwrite(STDOUT, sprintf("  Receita Total: R$ %s\n", number_format($totalReceita, 2, ',', '.')));
        fwrite(STDOUT, sprintf("  Despesa Total: R$ %s\n", number_format($totalDespesa, 2, ',', '.')));
        fwrite(STDOUT, sprintf("  Resultado:     R$ %s\n", number_format($totalReceita - $totalDespesa, 2, ',', '.')));
        fwrite(STDOUT, PHP_EOL);

        fwrite(STDOUT, str_repeat('=', 160).PHP_EOL);
        fwrite(STDOUT, 'DETALHE TERRENO — meses com despesa de terreno > 0'.PHP_EOL);
        fwrite(STDOUT, "cols: FINANC(custo_terreno+parceria) | PERM_FIS | COMISSAO | PGTO_LOTE | TOTAL_TERRENO | receita_RP | receita_RT | receita_MO".PHP_EOL);
        fwrite(STDOUT, str_repeat('-', 160).PHP_EOL);
        $count = 0;
        foreach ($fluxo as $mes => $linha) {
            $t = $linha['despesas']['terreno'] ?? [];
            if (($t['total_terreno'] ?? 0) <= 0.01) continue;
            if ($count++ >= 25) break;
            $rpTotal = array_sum($linha['receitas']['recursos_proprios'] ?? []);
            $rt = $linha['receitas']['recebimento_terreno']['recebimento_total_terreno'] ?? 0;
            $mo = $linha['receitas']['medicao_obra']['recebimento_total_medicao'] ?? 0;
            $receitaTotal = $linha['receitas']['total'];
            $parceriaEstimada = round($receitaTotal * 0.08, 2);
            $financ = $t['valor_permuta_financeira'] ?? 0;
            $custoEstimado = round($financ - $parceriaEstimada, 2);
            fwrite(STDOUT, sprintf("%-8s %-11s financ=%12s parc_est=%12s custo_est=%12s perm_fis=%12s comiss=%10s pgto_lote=%10s total_ter=%12s RP=%12s RT=%10s MO=%10s\n",
                $mes, $linha['periodo'],
                number_format($financ, 0, ',', '.'),
                number_format($parceriaEstimada, 0, ',', '.'),
                number_format($custoEstimado, 0, ',', '.'),
                number_format($t['valor_permuta_fisica'] ?? 0, 0, ',', '.'),
                number_format($t['valor_comissao'] ?? 0, 0, ',', '.'),
                number_format($t['valor_pgto_por_lote'] ?? 0, 0, ',', '.'),
                number_format($t['total_terreno'] ?? 0, 0, ',', '.'),
                number_format($rpTotal, 0, ',', '.'),
                number_format($rt, 0, ',', '.'),
                number_format($mo, 0, ',', '.'),
            ));
        }
        fwrite(STDOUT, PHP_EOL);
        fwrite(STDOUT, 'LEGENDA: financ = custoTerreno + parceria | parc_est = 8% × receita_total | custo_est = financ - parc_est'.PHP_EOL);
        fwrite(STDOUT, '         total_terreno = custoTerreno + parceria + perm_fis + comissao (sem pgto_lote)'.PHP_EOL);
        fwrite(STDOUT, PHP_EOL);

        // ============================================================
        // ASSERTIONS: Validação contra planilha (R$) — 1 produto
        // ============================================================
        $dre = $resultado['dre_itens'];
        $ind = $resultado['indicadores'];

        // VGV e Receitas
        $this->assertEqualsWithDelta(192400000, (float) $dre['receita_total_vendas'], 100, 'VGV LRG s/Terrenista');
        $this->assertGreaterThan(4000000, $dre['juros_correcoes'], 'Juros+Correções > 4M');
        $this->assertLessThan(6000000, $dre['juros_correcoes'], 'Juros+Correções < 6M');

        // Custos Diretos
        $this->assertEqualsWithDelta(90019300, ($dre['infra_casas'] + $dre['infra_lotes'] + $dre['area_comum'] + $dre['canteiro_total'] + $dre['contrapartidas']), 1000, 'Obra Total');
        $this->assertEqualsWithDelta(2200000, (float) $dre['incorporacao'], 100, 'Incorporação');
        $this->assertEqualsWithDelta(4219400, ($dre['mo_administrativa_total'] + $dre['seguros'] + $dre['assistencia_tecnica']), 2000, 'MO+Seguros+Assist');

        // Despesas Operacionais
        $this->assertEqualsWithDelta(10120000, (float) $dre['despesas_comerciais'], 100, 'Despesas Comerciais');
        $this->assertGreaterThan(0, (float) $dre['marketing'], 'Marketing deve ser > 0');
        $this->assertEqualsWithDelta(4526400, ($dre['itbi_iptu'] + $dre['registro']), 100, 'ITBI+Registro');

        // Resultado
        $this->assertEqualsWithDelta(3873400, $dre['juros_pj'], 20000, 'Juros PJ');
        $this->assertGreaterThan(30000000, $dre['lucro_liquido_projeto'], 'Lucro Líquido > 30M');
        $this->assertLessThan(35000000, $dre['lucro_liquido_projeto'], 'Lucro Líquido < 35M');

        // Indicadores
        $this->assertGreaterThan(15, $ind['margem_liquida_percentual'], 'Margem Líquida > 15%');
        $this->assertLessThan(20, $ind['margem_liquida_percentual'], 'Margem Líquida < 20%');

        $this->assertArrayHasKey('dre_itens', $resultado);
        $this->assertGreaterThan(0, $dre['receita_total_vendas'] ?? 0, 'Receita total deve ser > 0');
    }

    private function exportarResultadoParaExcel(
        array $resultado,
        string $prefixoArquivo,
        int $terrenoId,
        int $viabilidadeId
    ): string {
        $diretorio = storage_path('app/testing/viabilidade');

        if (! is_dir($diretorio) && ! mkdir($diretorio, 0777, true) && ! is_dir($diretorio)) {
            throw new \RuntimeException('Nao foi possivel criar o diretorio de exportacao da viabilidade.');
        }

        $arquivo = sprintf(
            '%s/%s-terreno-%d-viabilidade-%d.xlsx',
            $diretorio,
            $prefixoArquivo,
            $terrenoId,
            $viabilidadeId
        );

        $spreadsheet = new Spreadsheet();

        $this->preencherAbaTabular(
            $spreadsheet->getActiveSheet(),
            'Fluxo Mensal',
            $this->montarLinhasFluxoMensal($resultado['fluxo_mensal'] ?? [])
        );

        $this->adicionarAbaChaveValor($spreadsheet, 'DRE Itens', $resultado['dre_itens'] ?? []);
        $this->adicionarAbaChaveValor($spreadsheet, 'DRE Caixa', $resultado['dre_caixa'] ?? []);
        $this->adicionarAbaChaveValor($spreadsheet, 'DRE Contabil POC', $resultado['dre_contabil_poc'] ?? []);
        $this->adicionarAbaChaveValor(
            $spreadsheet,
            'POC Mensal Blocos',
            $resultado['dre_contabil_poc_mensal_blocos'] ?? []
        );
        $this->adicionarAbaChaveValor($spreadsheet, 'Ponte Reconc.', $resultado['ponte_reconciliacao'] ?? []);

        if (isset($resultado['indicadores']) && is_array($resultado['indicadores'])) {
            $this->adicionarAbaChaveValor($spreadsheet, 'Indicadores', $resultado['indicadores']);
        }

        if (array_key_exists('vgv', $resultado) || array_key_exists('custoTotal', $resultado)) {
            $this->adicionarAbaChaveValor($spreadsheet, 'Resumo', [
                'terreno_id' => $terrenoId,
                'viabilidade_id' => $viabilidadeId,
                'vgv' => $resultado['vgv'] ?? null,
                'custo_total' => $resultado['custoTotal'] ?? null,
            ]);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($arquivo);
        $spreadsheet->disconnectWorksheets();

        return $arquivo;
    }

    private function adicionarAbaChaveValor(Spreadsheet $spreadsheet, string $titulo, array $dados): void
    {
        $linhas = [];

        foreach ($this->achatarArray($dados) as $chave => $valor) {
            $linhas[] = [
                'chave' => $chave,
                'valor' => $this->normalizarValorPlanilha($valor),
            ];
        }

        $this->preencherAbaTabular($spreadsheet->createSheet(), $titulo, $linhas);
    }

    /**
     * @param  list<array<string, scalar|null>>  $linhas
     */
    private function preencherAbaTabular(Worksheet $sheet, string $titulo, array $linhas): void
    {
        $sheet->setTitle($this->sanitizarTituloAba($titulo));

        if ($linhas === []) {
            $sheet->setCellValue('A1', 'Sem dados');

            return;
        }

        $cabecalhos = [];

        foreach ($linhas as $linha) {
            foreach (array_keys($linha) as $coluna) {
                if (! in_array($coluna, $cabecalhos, true)) {
                    $cabecalhos[] = $coluna;
                }
            }
        }

        foreach ($cabecalhos as $indice => $coluna) {
            $sheet->setCellValue([$indice + 1, 1], $coluna);
        }

        $linhaPlanilha = 2;

        foreach ($linhas as $linha) {
            foreach ($cabecalhos as $indice => $coluna) {
                $sheet->setCellValue(
                    [$indice + 1, $linhaPlanilha],
                    $this->normalizarValorPlanilha($linha[$coluna] ?? null)
                );
            }

            $linhaPlanilha++;
        }

        $sheet->freezePane('A2');
        $this->ajustarLarguraColunas($sheet, count($cabecalhos));
    }

    /**
     * @param  array<array-key, array<array-key, mixed>>  $fluxoMensal
     * @return list<array<string, scalar|null>>
     */
    private function montarLinhasFluxoMensal(array $fluxoMensal): array
    {
        $linhas = [];

        foreach ($fluxoMensal as $mes => $dadosMes) {
            $linha = ['mes' => $mes];

            foreach ($this->achatarArray($dadosMes) as $chave => $valor) {
                $linha[$chave] = $this->normalizarValorPlanilha($valor);
            }

            $linhas[] = $linha;
        }

        return $linhas;
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @return array<string, mixed>
     */
    private function achatarArray(array $dados, string $prefixo = ''): array
    {
        if ($dados === []) {
            return $prefixo === '' ? [] : [$prefixo => '[]'];
        }

        $achatado = [];

        foreach ($dados as $chave => $valor) {
            $chaveAtual = $prefixo === '' ? (string) $chave : $prefixo.'.'.$chave;

            if (is_array($valor)) {
                foreach ($this->achatarArray($valor, $chaveAtual) as $subChave => $subValor) {
                    $achatado[$subChave] = $subValor;
                }

                continue;
            }

            $achatado[$chaveAtual] = $valor;
        }

        return $achatado;
    }

    private function normalizarValorPlanilha(mixed $valor): mixed
    {
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        if ($valor === null) {
            return '';
        }

        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_object($valor)) {
            return method_exists($valor, 'format')
                ? $valor->format('Y-m-d H:i:s')
                : json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $valor;
    }

    private function logDetalhesNested(array $detalhes, string $prefixo, string $ident = ''): void
    {
        foreach ($detalhes as $chave => $valor) {
            if (is_array($valor)) {
                $this->logDetalhesNested($valor, $prefixo, $ident.$chave.'.');
            } elseif (is_numeric($valor) && abs((float) $valor) > 0.01) {
                $caminho = $ident.$chave;
                fwrite(STDOUT, str_pad('', 26)."  {$prefixo}: {$caminho}: ".number_format((float) $valor, 0, ',', '.').PHP_EOL);
            }
        }
    }

    private function ajustarLarguraColunas(Worksheet $sheet, int $totalColunas): void
    {
        for ($indice = 1; $indice <= $totalColunas; $indice++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }
    }

    private function sanitizarTituloAba(string $titulo): string
    {
        $titulo = preg_replace('/[\\\\\\/?*:\\[\\]]/', ' ', $titulo) ?? 'Aba';
        $titulo = trim($titulo);

        if ($titulo === '') {
            $titulo = 'Aba';
        }

        return substr($titulo, 0, 31);
    }

    private function migrarTabelasTenantViabilidade(): void
    {
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/0001_01_01_000005_create_terrenos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_12_02_184006_create_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_11_13_161116_create_terreno_produto_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_02_07_000000_create_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_03_20_000000_add_viabilidade_campos_planilha.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_26_212214_add_data_lancamento_to_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_000001_add_taxas_cef_to_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_195000_create_premissas_viabilidade_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_200000_add_versionamento_e_snapshot.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_210000_remove_global_fields_from_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_211000_add_missing_fields_to_premissas_viabilidade_table.php']);
        $this->garantirParametrosComerciaisDetalhadosEmPremissas();
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_05_06_130000_remove_legacy_fields_from_produtos_and_premissas.php']);

        $this->popularPremissasPadrao();
    }

    private function garantirParametrosComerciaisDetalhadosEmPremissas(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table): void {
            if (! Schema::hasColumn('premissas_viabilidade', 'gastos_mensais_stand')) {
                $table->decimal('gastos_mensais_stand', 8, 4)->default(0.0001)->after('mobilia_decoracao');
            }

            if (! Schema::hasColumn('premissas_viabilidade', 'comissao_house_percentual')) {
                $table->decimal('comissao_house_percentual', 8, 2)->default(3.00)->after('gastos_mensais_stand');
            }

            if (! Schema::hasColumn('premissas_viabilidade', 'comissao_imobiliarias_percentual')) {
                $table->decimal('comissao_imobiliarias_percentual', 8, 2)->default(3.50)->after('comissao_house_percentual');
            }

            if (! Schema::hasColumn('premissas_viabilidade', 'percentual_vendas_house')) {
                $table->decimal('percentual_vendas_house', 8, 2)->default(50.00)->after('comissao_imobiliarias_percentual');
            }

            if (! Schema::hasColumn('premissas_viabilidade', 'pagamento_comissao_venda')) {
                $table->decimal('pagamento_comissao_venda', 8, 2)->default(50.00)->after('bonus_equipe_comercial');
            }

            if (! Schema::hasColumn('premissas_viabilidade', 'marketing_lancamento')) {
                $table->decimal('marketing_lancamento', 8, 2)->default(25.00)->after('marketing');
            }
        });
    }

    private function popularPremissasPadrao(): void
    {
        $agora = now();

        DB::table('premissas_viabilidade')->insert([
            'nome' => 'Padrão CEF (teste)',
            'perfil_financiamento' => 'cef',
            'ativo' => true,
            'vigente_em' => $agora->toDateString(),
            'versao' => 1,
            'pis_cofins' => 4.0,
            'iss' => 0.0,
            'outros_impostos' => 0.5,
            'comissao' => 0.0,
            'parceria_vgv' => 0.0,
            'infra_nao_incidente' => 1.0,
            'incorporacao' => 1.0,
            'incorp_ri' => 30.0,
            'incorp_entrega' => 15.0,
            'incorp_ate_lancamento' => 80.0,
            'obra_ate_lancamento' => 1.0,
            'area_comum' => 0.0,
            'contrapartidas' => 0.0,
            'canteiro_mensal' => 85715.0,
            'mo_administrativa' => 62502.0,
            'seguros' => 0.5,
            'assistencia_tecnica' => 1.0,
            'despesas_comerciais' => 5.0,
            'stand_vendas' => 0.0,
            'mobilia_decoracao' => 90000.0,
            'gastos_mensais_stand' => 0.0001,
            'comissao_house_percentual' => 3.0,
            'comissao_imobiliarias_percentual' => 3.5,
            'percentual_vendas_house' => 50.0,
            'ajuda_custo_gerente' => 5000.0,
            'ajuda_custo_gerente_regional' => 2733.0,
            'reembolso_logistica' => 5000.0,
            'bonus_cca' => 350.0,
            'bonus_gerente' => 0.3,
            'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05,
            'bonus_gestor_comercial' => 0.05,
            'pagamento_comissao_venda' => 50.0,
            'pagamento_comissao_desligamento' => 50.0,
            'parcelamento_comissao_meses' => 18,
            'marketing' => 1.0,
            'marketing_lancamento' => 25.0,
            'marketing_inicio_antes_lancamento' => 3,
            'itbi_iptu' => 1.1,
            'registro' => 2500.0,
            'custo_contratacao_cef' => 24000.0,
            'custo_medicao_cef' => 2000.0,
            'contratos_cef' => 300.0,
            'produtos_cef' => 0.5,
            'outras_despesas_financeiras' => 0.3,
            'despesas_onerosas_bancos' => 10.0,
            'prazo_obra' => 36,
            'compra_terreno' => 0.0,
            'porcentagem_lote_proprietario' => 10.0,
            'taxa_juros_pj' => 10.5,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'percentual_antecipacao_pj' => 10.0,
            'aporte_adicional_mensal' => 0.0,
            'devolucao_aporte_percentual' => 20.0,
            'distribuicao_lucros_percentual_obra' => 100.0,
            'taxa_exposicao_aplicada' => 12.5,
            'inadimplencia' => 0.10,
            'atraso_meses' => 2,
            'taxa_perda' => 0.02,
            'meses_incorporacao' => 18,
            'meses_lancamento' => 6,
            'meses_entrega' => 1,
            'meses_pos_obra' => 60,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }
}
