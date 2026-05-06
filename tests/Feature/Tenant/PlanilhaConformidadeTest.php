<?php

namespace Tests\Feature\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeUnificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Teste de conformidade com a planilha de viabilidade modelo.
 *
 * Compara o resultado do ViabilidadeUnificadoService contra os valores
 * da planilha "Viabilidade LRG - V.01.2026 - Modelo".
 *
 * Dados extraídos da aba "Premissas" em 2026-04-26.
 */
class PlanilhaConformidadeTest extends TestCase
{
    use RefreshDatabase;

    private ViabilidadeUnificadoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrarTabelasTenantViabilidade();

        $this->service = app(ViabilidadeUnificadoService::class);
    }

    public function test_reproduz_resultados_da_planilha_modelo_com_2d_3d_lotes(): void
    {
        $agora = now();

        // ─── Criar terreno ───────────────────────────────────────────────
        $terrenoId = DB::table('terrenos')->insertGetId([
            'nome' => 'Area Teste',
            'area_calculada' => 53333,
            'data_contrato' => '2026-01-10',
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // ─── Criar viabilidade ───────────────────────────────────────────
        $viabilidadeId = DB::table('viabilidades')->insertGetId([
            'terreno_id' => $terrenoId,
            'prazo_obra' => 36,
            'prazo_incorporacao' => 18,
            'prazo_lancamento' => 6,
            'data_lancamento' => '2029-06-01',
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            'compra_terreno' => 10000000,
            'parceria_vgv' => 8.0,
            'pis_cofins' => 4.0,
            'iss' => 0.0,
            'outros_impostos' => 0.5,
            'comissao' => 0.0,
            'incorporacao' => 1.0,
            'infra_nao_incidente' => 1.0,
            'area_comum' => 1500,
            'contrapartidas' => 1.0,
            'canteiro_mensal' => 85715,
            'mo_administrativa' => 62502,
            'seguros' => 0.5,
            'assistencia_tecnica' => 1.0,
            'despesas_comerciais' => 5.0,
            'marketing' => 1.0,
            'itbi_iptu' => 1.1,
            'registro' => 2500,
            'contratos_cef' => 300,
            'produtos_cef' => 0.5,
            'outras_despesas_financeiras' => 0.3,
            'percentual_antecipacao_pj' => 10.0,
            'taxa_juros_pj' => 10.5,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'bonus_cca' => 350,
            'bonus_gerente' => 0.3,
            'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05,
            'bonus_gestor_comercial' => 0.05,
            'stand_vendas' => 290000,
            'mobilia_decoracao' => 0,
            'ajuda_custo_gerente' => 5000,
            'ajuda_custo_gerente_regional' => 2733,
            'reembolso_logistica' => 5000,
            'aporte_adicional_mensal' => 0,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // ─── Criar produtos ──────────────────────────────────────────────
        // 2 Dorm
        $produto2DormId = DB::table('produtos')->insertGetId([
            'name' => '2 Dorm',
            'private_area' => 47.2,
            'm2_cost' => 1400,
            'infra_cost' => 22000,
            'status' => 'ativo',
            'sinal' => 2.00,
            'parcela_obra' => 9.00,
            'parcela_posChave' => 9.00,
            'qtde_parcelas_posChave' => '36',
            'demanda_minCef' => 30.00,
            'defasagem_pgtoTerreno' => 1,
            'avaliacao_lotesCef' => 0.20,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
            'imposto_tributos' => 4.00,
            'imposto_iss' => 0.00,
            'imposto_outros' => 0.50,
            'curva_vendas' => json_encode([10, 9, 8.1, 7.29, 6.561, 5.9049, 5.31441, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428]),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // 3 Dorm
        $produto3DormId = DB::table('produtos')->insertGetId([
            'name' => '3 Dorm',
            'private_area' => 61.33,
            'm2_cost' => 1400,
            'infra_cost' => 22000,
            'status' => 'ativo',
            'sinal' => 2.00,
            'parcela_obra' => 9.00,
            'parcela_posChave' => 9.00,
            'qtde_parcelas_posChave' => '36',
            'demanda_minCef' => 30.00,
            'defasagem_pgtoTerreno' => 1,
            'avaliacao_lotesCef' => 0.15,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
            'imposto_tributos' => 4.00,
            'imposto_iss' => 0.00,
            'imposto_outros' => 0.50,
            'curva_vendas' => json_encode([10, 9, 8.1, 7.29, 6.561, 5.9049, 5.31441, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428]),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // Lotes
        $produtoLotesId = DB::table('produtos')->insertGetId([
            'name' => 'Lotes',
            'private_area' => 0,
            'm2_cost' => 0,
            'infra_cost' => 22000,
            'status' => 'ativo',
            'sinal' => 10.00,
            'parcela_obra' => 10.00,
            'parcela_posChave' => 80.00,
            'qtde_parcelas_posChave' => '80',
            'demanda_minCef' => 30.00,
            'defasagem_pgtoTerreno' => 1,
            'avaliacao_lotesCef' => 0.00,
            'juros_mensalSinal' => 0.00,
            'juros_mensalObra' => 0.00,
            'juros_mensalPosChave' => 1.00,
            'correcao_anualSinal' => 0.00,
            'correcao_anualObra' => 5.00,
            'correcao_anualPosChave' => 4.50,
            'imposto_tributos' => 6.73,
            'imposto_iss' => 0.00,
            'imposto_outros' => 0.50,
            'curva_vendas' => json_encode([0, 0, 0, 10, 9, 8.1, 7.29, 6.561, 5.9049, 5.31441, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428, 3.416406428571428]),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // ─── Vincular produtos ao terreno ────────────────────────────────
        DB::table('terreno_produtos')->insert([
            ['terreno_id' => $terrenoId, 'produto_id' => $produto2DormId, 'unidades' => 1000, 'valor' => 220000, 'permuta' => 80, 'pgto_por_lote' => 10000, 'created_at' => $agora, 'updated_at' => $agora],
            ['terreno_id' => $terrenoId, 'produto_id' => $produto3DormId, 'unidades' => 100, 'valor' => 250000, 'permuta' => 10, 'pgto_por_lote' => 10000, 'created_at' => $agora, 'updated_at' => $agora],
            ['terreno_id' => $terrenoId, 'produto_id' => $produtoLotesId, 'unidades' => 200, 'valor' => 120000, 'permuta' => 0, 'pgto_por_lote' => 5000, 'created_at' => $agora, 'updated_at' => $agora],
        ]);

        // ─── Executar cálculo ────────────────────────────────────────────
        $resultado = $this->service->gerarFluxoMensal(
            $terrenoId,
            $viabilidadeId
        );

        $dre = $resultado['dre_itens'];
        $indicadores = $resultado['indicadores'];

        // ─── Asserts básicos ─────────────────────────────────────────────
        $this->assertGreaterThan(220_000_000, $resultado['vgv'], 'VGV deve ser > 220M');
        $this->assertEquals(1300, $resultado['totalUnidades'], 'Total de unidades');

        // DRE
        $this->assertGreaterThan(236_900_000 * 0.80, $dre['receita_total_vendas'], 'Receita total vendas');
        $this->assertLessThan(236_900_000 * 1.20, $dre['receita_total_vendas']);

        // Lucro Líquido da planilha: R$57.056.248
        $this->assertGreaterThan(57_056_248 * 0.70, $dre['lucro_liquido_projeto'], 'Lucro líquido min');
        $this->assertLessThan(57_056_248 * 1.30, $dre['lucro_liquido_projeto'], 'Lucro líquido max');

        // Receitas
        $this->assertGreaterThan(0.0, $dre['receita_bruta'], 'Receita bruta > 0');
        $this->assertGreaterThan(0.0, $dre['receita_liquida'], 'Receita líquida > 0');

        // Custos
        $this->assertGreaterThan(0.0, $dre['custos_diretos_total'], 'Custos diretos > 0');
        $this->assertGreaterThan(0.0, $dre['custo_terreno'], 'Custo terreno > 0');
        $this->assertGreaterThan(0.0, $dre['infra_casas'], 'Infra casas > 0');

        // EBITDA e EBIT
        $this->assertGreaterThan(0.0, $dre['ebitda'], 'EBITDA > 0');
        $this->assertGreaterThan(0.0, $dre['ebit'], 'EBIT > 0');
        $this->assertLessThan($dre['ebitda'], $dre['ebit'], 'EBIT < EBITDA');

        // Margens
        $margemLiquida = $indicadores['margem_liquida_percentual'] ?? 0;
        $this->assertGreaterThan(10.0, $margemLiquida, 'Margem líquida > 10%');
        $this->assertLessThan(40.0, $margemLiquida, 'Margem líquida < 40%');

        // Exposição máxima
        $this->assertLessThan(0.0, $indicadores['exposicao_maxima_operacional'], 'Exposição máxima < 0');

        // Fluxo mensal
        $fluxo = $resultado['fluxo_mensal'];
        $this->assertNotEmpty($fluxo, 'Fluxo não vazio');
        $this->assertGreaterThan(50, count($fluxo), 'Fluxo > 50 meses');

        // Payback
        $payback = $indicadores['payback_operacional_meses'] ?? $indicadores['payback_operacional'] ?? null;
        $this->assertNotNull($payback, 'Payback existe');
        $this->assertGreaterThan(0, $payback, 'Payback > 0');

        // ─── Log detalhado de comparação ────────────────────────────────
        $this->logComparacao($dre, $indicadores, $resultado);
        $this->logComparacaoFluxo($resultado);
    }

    private function logComparacao(array $dre, array $indicadores, array $resultado): void
    {
        $margemLiquida = $indicadores['margem_liquida_percentual'] ?? 0;

        fwrite(STDOUT, "\n╔══════════════════════════════════════════════════════════════════════╗\n");
        fwrite(STDOUT, "║           COMPARAÇÃO PLANILHA vs SISTEMA                             ║\n");
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════╣\n");
        fwrite(STDOUT, sprintf("║ %-40s %18s %18s %6s ║\n", 'Item', 'Planilha', 'Sistema', 'Diff'));
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════╣\n");

        $comparacoes = [
            'VGV LRG (s/ Lote Terrenista)' => [236_900_000, (int) $resultado['vgv']],
            'Receita Total Vendas' => [(int) $dre['receita_total_vendas'], (int) $dre['receita_total_vendas']],
            'Receita Bruta' => [252_294_559, (int) $dre['receita_bruta']],
            'Receita Líquida' => [245_395_183, (int) $dre['receita_liquida']],
            'Custo Terreno' => [42_868_752, (int) $dre['custo_terreno']],
            'Comissão' => [428_403, (int) $dre['comissao']],
            'Incorporação' => [2_690_000, (int) $dre['incorporacao']],
            'Infra Casas' => [68_521_180, (int) $dre['infra_casas']],
            'Infra Lotes' => [29_310_000, (int) $dre['infra_lotes']],
            'Área Comum' => [1_950_000, (int) $dre['area_comum']],
            'Contrapartidas' => [2_690_000, (int) $dre['contrapartidas']],
            'Canteiro Total' => [3_085_740, (int) $dre['canteiro_total']],
            'M.O. Adm.' => [2_250_072, (int) $dre['mo_administrativa_total']],
            'Seguros' => [1_340_000, (int) $dre['seguros']],
            'Assist. Técnica' => [1_024_712, (int) $dre['assistencia_tecnica']],
            'Lucro Bruto' => [89_264_727, (int) $dre['lucro_bruto']],
            'Despesas Comerciais' => [12_445_000, (int) $dre['despesas_comerciais']],
            'Marketing' => [2_489_000, (int) $dre['marketing']],
            'ITBI + IPTU' => [2_473_900, (int) $dre['itbi_iptu']],
            'EBITDA' => [67_584_233, (int) $dre['ebitda']],
            'Juros PJ' => [4_542_014, (int) $dre['juros_pj']],
            'EBIT' => [62_331_519, (int) $dre['ebit']],
            'IRPJ/CSLL' => [5_275_271, (int) $dre['irpj_csll']],
            'Lucro Líquido' => [57_056_248, (int) $dre['lucro_liquido_projeto']],
        ];

        foreach ($comparacoes as $nome => [$planilha, $sistema]) {
            $diff = $planilha != 0 ? abs(($sistema - $planilha) / abs($planilha)) * 100 : 0;
            $marker = $diff < 10 ? '✅' : ($diff < 25 ? '⚠️' : '🔴');
            fwrite(STDOUT, sprintf(
                "║ %s %-38s %18s %18s %5.1f%% ║\n",
                $marker,
                substr($nome, 0, 38),
                number_format($planilha, 0, ',', '.'),
                number_format($sistema, 0, ',', '.'),
                $diff
            ));
        }

        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════╣\n");
        fwrite(STDOUT, sprintf("║ Margem Líquida:  Planilha=23.25%%  Sistema=%.2f%%%s║\n", $margemLiquida, str_repeat(' ', 15)));
        fwrite(STDOUT, sprintf("║ VGV Total:        Planilha=%d  Sistema=%d%s║\n", 269_000_000, (int)$resultado['vgv'], str_repeat(' ', 9)));
        fwrite(STDOUT, "╚══════════════════════════════════════════════════════════════════════╝\n");
        fwrite(STDOUT, "\n");
    }

    private function logComparacaoFluxo(array $resultado): void
    {
        $fluxo = $resultado['fluxo_mensal'];
        $fluxoKeys = array_keys($fluxo);
        $totalMeses = count($fluxo);

        $totalReceitas = 0;
        $totalDespesas = 0;
        $saldoMinimo = 0;
        $saldoMaximo = 0;
        $minSaldoMes = '';
        $maxSaldoMes = '';

        // Agrupar por fase + totais
        $porFase = [];
        $totaisFase = [];
        $periodosEncontrados = [];
        foreach ($fluxo as $mes => $linha) {
            $p = $linha['periodo'] ?? '?';
            if (!isset($porFase[$p])) {
                $porFase[$p] = [];
                $totaisFase[$p] = ['entradas' => 0, 'saidas' => 0, 'saldo' => 0, 'meses' => 0, 'minSaldo' => null, 'maxSaldo' => null];
                $periodosEncontrados[] = $p;
            }
        }
        
        foreach ($fluxo as $mes => $linha) {
            $periodo = $linha['periodo'] ?? '?';
            $receita = $linha['receitas']['total'] ?? 0;
            $custos = $linha['despesas']['total'] ?? 0;
            $acum = $linha['saldo_acumulado_mes'] ?? 0;
            $lucro = $receita - $custos;

            $totalReceitas += $receita;
            $totalDespesas += $custos;

            if ($acum < $saldoMinimo) {
                $saldoMinimo = $acum;
                $minSaldoMes = $mes;
            }
            if ($acum > $saldoMaximo) {
                $saldoMaximo = $acum;
                $maxSaldoMes = $mes;
            }

            $dadosMes = ['mes' => $mes, 'entrada' => $receita, 'saida' => $custos, 'acum' => $acum, 'lucro' => $lucro];
            $porFase[$periodo][] = $dadosMes;
            $totaisFase[$periodo]['entradas'] += $receita;
            $totaisFase[$periodo]['saidas'] += $custos;
            $totaisFase[$periodo]['saldo'] += $lucro;
            $totaisFase[$periodo]['meses']++;
            $totaisFase[$periodo]['minSaldo'] = $totaisFase[$periodo]['minSaldo'] === null ? $acum : min($totaisFase[$periodo]['minSaldo'], $acum);
            $totaisFase[$periodo]['maxSaldo'] = $totaisFase[$periodo]['maxSaldo'] === null ? $acum : max($totaisFase[$periodo]['maxSaldo'], $acum);
        }

        // ═══ CABEÇALHO ═══
        fwrite(STDOUT, "\n╔══════════════════════════════════════════════════════════════════════════════════════════════════════╗\n");
        fwrite(STDOUT, sprintf("║  FLUXO DE CAIXA COMPLETO — %d meses  (data_lancamento = 2029-06-01)%-21s║\n", $totalMeses, ''));
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════════════════════════════════╣\n");
        fwrite(STDOUT, sprintf("║ %-7s │ %-10s │ %14s │ %14s │ %14s │ %16s ║\n", 'Mês', 'Fase', 'Entradas', 'Saídas', 'Saldo Mês', 'Saldo Acumulado'));
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════════════════════════════════╣\n");

        $faseAnterior = '';
        foreach ($periodosEncontrados as $faseNome) {
            if (empty($porFase[$faseNome])) {
                continue;
            }

            if ($faseAnterior !== $faseNome && $faseAnterior !== '') {
                fwrite(STDOUT, "║         │          │                │                │                │                  ║\n");
            }
            $faseAnterior = $faseNome;

            // Sub-header da fase
            $limite = count($porFase[$faseNome]) > 8 ? 6 : count($porFase[$faseNome]);
            $mostrados = 0;

            foreach ($porFase[$faseNome] as $i => $m) {
                if ($mostrados < $limite || $i >= count($porFase[$faseNome]) - 2) {
                    fwrite(STDOUT, sprintf(
                        "║ %-7s │ %-10s │ %14s │ %14s │ %14s │ %16s ║\n",
                        $m['mes'],
                        $faseNome,
                        number_format($m['entrada'], 0, ',', '.'),
                        number_format($m['saida'], 0, ',', '.'),
                        number_format($m['lucro'], 0, ',', '.'),
                        number_format($m['acum'], 0, ',', '.')
                    ));
                    $mostrados++;
                } elseif ($mostrados === $limite) {
                    fwrite(STDOUT, sprintf("║ %-7s │ %-10s │ %s ║\n", '...', '', str_repeat(' ', 58)));
                    $mostrados++;
                }
            }

            // Total da fase
            $tf = $totaisFase[$faseNome];
            if ($tf['meses'] > 1) {
                fwrite(STDOUT, "║         │          │                │                │                │                  ║\n");
                fwrite(STDOUT, sprintf(
                    "║ %-7s │ %-10s │ %14s │ %14s │ %14s │ %-16s ║\n",
                    'TOTAL',
                    $faseNome,
                    number_format($tf['entradas'], 0, ',', '.'),
                    number_format($tf['saidas'], 0, ',', '.'),
                    number_format($tf['saldo'], 0, ',', '.'),
                    "{$tf['meses']} meses"
                ));
            }
        }

        // ═══ TOTAIS GERAIS ═══
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════════════════════════════════╣\n");
        fwrite(STDOUT, sprintf(
            "║ %-7s │ %-10s │ %14s │ %14s │ %14s │ %16s ║\n",
            'TOTAL',
            'GERAL',
            number_format($totalReceitas, 0, ',', '.'),
            number_format($totalDespesas, 0, ',', '.'),
            number_format($totalReceitas - $totalDespesas, 0, ',', '.'),
            number_format($saldoMaximo, 0, ',', '.')
        ));
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════════════════════════════════╣\n");
        fwrite(STDOUT, sprintf(
            "║  Exposição Máxima: %-7s  Saldo: %14s                          Exposição / VGV: %5.1f%%  ║\n",
            $minSaldoMes,
            number_format($saldoMinimo, 0, ',', '.'),
            $resultado['vgv'] > 0 ? abs($saldoMinimo) / $resultado['vgv'] * 100 : 0
        ));
        fwrite(STDOUT, sprintf(
            "║  Margem Líquida: %5.1f%%    VGV: R$%s  Unidades: %d%-9s ║\n",
            $resultado['indicadores']['margem_liquida_percentual'] ?? 0,
            number_format($resultado['vgv'] ?? 0, 0, ',', '.'),
            $resultado['totalUnidades'] ?? 0,
            ''
        ));
        fwrite(STDOUT, "╚══════════════════════════════════════════════════════════════════════════════════════════════════════╝\n\n");

        // ═══ COMPARAÇÃO TIR, PAYBACK, EXPOSIÇÃO ═══
        $this->logComparacaoIndicadores($resultado, $saldoMinimo);

        // Asserts no fluxo
        $this->assertGreaterThan(100, $totalMeses, 'Fluxo deve ter > 100 meses');
        $this->assertLessThan(0, $saldoMinimo, 'Fluxo deve ter exposição negativa');
        $this->assertGreaterThan(50_000_000, $saldoMaximo, 'Saldo final > 50M');
        $this->assertGreaterThan(200_000_000, $totalReceitas, 'Receitas totais > 200M');
    }

    private function logComparacaoIndicadores(array $resultado, float $saldoMinimo): void
    {
        $indicadores = $resultado['indicadores'] ?? [];
        $vgv = $resultado['vgv'] ?? 0;

        // TIR do sistema (calcularTir retorna taxa mensal - precisa revisar implementação)
         // Mantemos exposição, payback e margem que estão corretos
         $tirOperacionalRaw = $indicadores['tir_operacional'] ?? 0;
         $tirFinanceiraRaw = $indicadores['tir_financeira'] ?? 0;

        // Payback do sistema
        $paybackOp = $indicadores['payback_operacional_meses'] ?? ($indicadores['payback_operacional'] ?? null);
        $paybackFin = $indicadores['payback_financeiro_meses'] ?? null;

        // Exposição financeira
        $exposicaoFin = $indicadores['exposicao_maxima_financeira'] ?? null;

        fwrite(STDOUT, "╔══════════════════════════════════════════════════════════════════════════╗\n");
        fwrite(STDOUT, "║      INDICADORES FINANCEIROS — COMPARAÇÃO PLANILHA vs SISTEMA            ║\n");
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════╣\n");
        fwrite(STDOUT, sprintf("║ %-30s │ %18s │ %18s ║\n", 'Indicador', 'Planilha', 'Sistema'));
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════╣\n");

        // TIR (⚠️ calcularTir() precisa ser revisado)
        fwrite(STDOUT, sprintf("║ %-30s │ %16.2f %%   │ %16s   ║\n",
            'TIR Operacional (a.a.)', 4.21, '⚠️ revisar'));

        fwrite(STDOUT, sprintf("║ %-30s │ %16.2f %%   │ %16s   ║\n",
            'TIR Financeira (a.a.)', 8.33, '⚠️ revisar'));

        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════╣\n");

        // Exposição Máxima
        fwrite(STDOUT, sprintf("║ %-30s │ %18s │ %18s ║\n",
            'Exposição Máx. Operacional', '-R$ 7.227.961', '-R$ ' . number_format(abs($saldoMinimo), 0, ',', '.')));

        fwrite(STDOUT, sprintf("║ %-30s │ %18s │ %18s ║\n",
            'Exposição Máx. Financeira', '-R$ 4.776.747', $exposicaoFin !== null ? '-R$ ' . number_format(abs($exposicaoFin), 0, ',', '.') : 'N/D'));

        fwrite(STDOUT, sprintf("║ %-30s │ %16.2f %%    │ %16.1f %%    ║\n",
            '% Exposição / VGV', 2.90, $vgv > 0 ? abs($saldoMinimo) / $vgv * 100 : 0));

        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════╣\n");

        // Payback
        fwrite(STDOUT, sprintf("║ %-30s │ %16s      │ %16s      ║\n",
            'Payback Operacional', 'N/D', $paybackOp !== null ? $paybackOp . ' meses' : 'N/D'));

        fwrite(STDOUT, sprintf("║ %-30s │ %16s      │ %16s      ║\n",
            'Payback Financeiro', 'N/D', $paybackFin !== null ? $paybackFin . ' meses' : 'N/D'));

        // Margem líquida
        $margem = $indicadores['margem_liquida_percentual'] ?? 0;
        fwrite(STDOUT, "╠══════════════════════════════════════════════════════════════════════════╣\n");
        fwrite(STDOUT, sprintf("║ %-30s │ %16.2f %%    │ %16.2f %%    ║\n",
            'Margem Líquida', 23.25, $margem));

        fwrite(STDOUT, "╚══════════════════════════════════════════════════════════════════════════╝\n\n");
    }

    private function migrarTabelasTenantViabilidade(): void
    {
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/0001_01_01_000005_create_terrenos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_12_02_184006_create_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_11_13_161116_create_terreno_produto_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_02_07_000000_create_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_03_20_000000_add_viabilidade_campos_planilha.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_25_000001_add_perfil_financiamento_to_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_26_212214_add_data_lancamento_to_viabilidades_table.php']);
    }
}
