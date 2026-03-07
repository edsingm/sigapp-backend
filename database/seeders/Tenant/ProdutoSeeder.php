<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Produto;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProdutoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Em produção, evitar TRUNCATE por causa de FKs (ex.: area_produtos -> products)
        // Limpar a tabela apenas em ambientes locais/de teste
        if (!app()->environment('production')) {
            // Limpeza total no ambiente local/de teste, removendo inclusive registros soft-deleted
            Produto::query()->forceDelete();
        }

        Produto::updateOrCreate(
            ['name' => 'Exemplo - 2 dorm - 47,2m2'],
            [
                'description' => '2 dormitórios, 47,2m2, com banheiro, sala 2 ambientes, cozinha e quintal',
                'image' => '',
                'private_area' => 47.2,
                'm2_cost' => 1350.00,
                'infra_cost' => 22000.00,
                'status' => 'ativo',
                'sinal' => 2.00,
                'parcela_obra' => 9.00,
                'parcela_posChave' => 9.00,
                'qtde_parcelas_posChave' => '36',
                'demanda_minCef' => 30.00,
                'defasagem_pgtoTerreno' => '2',
                'avaliacao_lotesCef' => 15.00,
                'juros_mensalSinal' => 0.00,
                'juros_mensalObra' => 0.00,
                'juros_mensalPosChave' => 1.00,
                'correcao_anualSinal' => 0.00,
                'correcao_anualObra' => 5.00,
                'correcao_anualPosChave' => 4.50,
                'imposto_tributos' => 4.00,
                'imposto_iss' => 0.00,
                'imposto_outros' => 0.50,
                'curva_vendas' => ['0' => 10, '1' => 9, '2' => 8.1, '3' => 7.3, '4' => 6.6, '5' => 5.9, '6' => 5.3, '7' => 3.4, '8' => 3.4, '9' => 3.4, '10' => 3.4, '11' => 3.4, '12' => 2.4, '13' => 3.4, '14' => 3.4, '15' => 3.4, '16' => 3.4, '17' => 3.4, '18' => 3.4, '19' => 3.4, '20' => 3.4],
                'incorp_ri' => 1.00,
                'incorp_entrega' => 2.00,
                'incorp_ateLancamento' => 3.00,
                'obra_ateLancamento' => 5.00,
                'assist_tecnica1' => 0.10,
                'assist_tecnica2' => 0.20,
                'assist_tecnica3' => 0.30,
                'assist_tecnica4' => 0.40,
                'assist_tecnica5' => 0.50,
                'meses_inicioConstrucao' => '1',
                'porcentagem_ConstrucaoStand' => 1.00,
                'gastos_mensaisStand' => 1000.00,
                'comissao_house' => 1.00,
                'porcentagem_comissaoHouse' => 40.00,
                'porcentagem_comissaoImobs' => 60.00,
                'pagto_comissaoNaVenda' => 80.00,
                'marketing_antesLancamento' => '10000.00',
                'marketing_lancamento' => 10000.00,
                'custo_contratacaoCef' => '10000.00',
                'pj_taxaJuros' => 10.00,
                'pj_carenciaPosObra' => '6',
                'pj_qtdeParcelasPosCarencia' => '24',
            ]
        );
    }
}
