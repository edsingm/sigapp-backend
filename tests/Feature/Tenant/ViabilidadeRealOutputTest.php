<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Viabilidade;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeUnificadoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'usar_antecipacao_pj' => true,
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
            'usar_antecipacao_pj' => true,
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

    private function migrarTabelasTenantViabilidade(): void
    {
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/0001_01_01_000005_create_terrenos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_12_02_184006_create_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_25_000002_add_baloes_to_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_11_13_161116_create_terreno_produto_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_02_07_000000_create_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_03_20_000000_add_viabilidade_campos_planilha.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_26_212214_add_data_lancamento_to_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_000001_add_taxas_cef_to_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_195000_create_premissas_viabilidade_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_200000_add_versionamento_e_snapshot.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_210000_remove_global_fields_from_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_04_27_211000_add_missing_fields_to_premissas_viabilidade_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_07_17_000000_add_usar_antecipacao_pj_to_viabilidades.php']);
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
