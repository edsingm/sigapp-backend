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
        $this->assertArrayHasKey('dre_contabil_poc_mensal_blocos', $resultado);
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

    private function migrarTabelasTenantViabilidade(): void
    {
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/0001_01_01_000005_create_terrenos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_12_02_184006_create_produtos_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2025_11_13_161116_create_terreno_produto_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_02_07_000000_create_viabilidades_table.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/tenant/2026_03_20_000000_add_viabilidade_campos_planilha.php']);
    }
}
