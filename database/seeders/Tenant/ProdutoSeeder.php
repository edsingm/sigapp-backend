<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Produto;
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
        if (! app()->environment('production')) {
            // Limpeza total no ambiente local/de teste, removendo inclusive registros soft-deleted
            Produto::query()->forceDelete();
        }

        Produto::updateOrCreate(
            ['name' => 'Exemplo - 2 dorm - 47,2m2'],
            [
                'description' => '2 dormitórios, 47,2m2, com banheiro, sala 2 ambientes, cozinha e quintal',
                'image' => '',
                'private_area' => 47.2, //decimal 5,2 -> tamanho do imóvel em metros quadrados
                'm2_cost' => 1400.00, //decimal 12,2 -> custo do metro quadrado em valor monetário
                'infra_cost' => 22000.00, //decimal 12,2 -> custo da infraestrutura em valor monetário
                'status' => 'ativo',
                'sinal' => 0.02, //decimal 5,2 -> porcentagem sobre o valor total do sinal do imóvel no financiamento, geralmente 20% de sinal
                'parcela_obra' => 0.09, //decimal 5,2 -> porcentagem sobre o valor total do sinal do imóvel no financiamento
                'parcela_posChave' => 0.09, //decimal 5,2 -> porcentagem sobre o valor total do sinal do imóvel no financiamento
                'qtde_parcelas_posChave' => 36, //integer -> quantidade maxima de parcelas após entrega de chaves
                'demanda_minCef' => 0.30, //decimal 5,2 -> demanda mínima para contratacao de PJ CEF
                'defasagem_pgtoTerreno' => 2, //integer -> quantidade de meses de defasagem para iniciar o pagamento do terreno
                'avaliacao_lotesCef' => 0.15, //decimal 5,2 -> porcentagem sobre o valor total do imóvel em avaliacao de lotes CEF
                'juros_mensalSinal' => 0.00, //decimal 5,2 -> juros mensal sobre o sinal do imóvel em percentual
                'juros_mensalObra' => 0.00, //decimal 5,2 -> juros mensal sobre a obra do imóvel em percentual
                'juros_mensalPosChave' => 0.01, //decimal 5,2 -> juros mensal sobre a chave do imóvel em percentual
                'correcao_anualSinal' => 0.00, //decimal 5,2 -> correção anual sobre o sinal do imóvel em percentual
                'correcao_anualObra' => 0.05, //decimal 5,2 -> correção anual sobre a obra do imóvel em percentual
                'correcao_anualPosChave' => 0.045, //decimal 5,2 -> correção anual sobre a chave do imóvel em percentual
                'curva_vendas' => [10, 9, 8.1, 7.3, 6.6, 5.9, 5.3, 3.4, 3.4, 3.4, 3.4, 3.4, 2.4, 3.4, 3.4, 3.4, 3.4, 3.4, 3.4, 3.4, 3.4],
                'baloes_anuais' => [
                    ['mes' => 12, 'percentual' => 3.0],
                    ['mes' => 24, 'percentual' => 3.0],
                ],
                'balao_entrega_modo' => 'saldo_restante',
                'incorp_ri' => 0.30, //decimal 5,2 -> porcentagem sobre o valor total de incorporaçao que será gasto no RI do projeto
                'incorp_entrega' => 0.15, //decimal 5,2 -> porcentagem sobre o valor total de incorporaçao que será gasto na entrega de chaves do projeto
                'incorp_ateLancamento' => 0.80, //decimal 5,2 -> porcentagem sobre o valor restante do total de incorporaçao que será gasto até o lançamento do projeto
                'obra_ateLancamento' => 0.01, //decimal 5,2 -> porcentagem de obra executada antes do lançamento do projeto
                'assist_tecnica1' => 0.05, //decimal 5,2 -> porcentagem de assistência tecnica no ano 1
                'assist_tecnica2' => 0.02, //decimal 5,2 -> porcentagem de assistência tecnica no ano 2
                'assist_tecnica3' => 0.01, //decimal 5,2 -> porcentagem de assistência tecnica no ano 3
                'assist_tecnica4' => 0.01, //decimal 5,2 -> porcentagem de assistência tecnica no ano 4
                'assist_tecnica5' => 0.01, //decimal 5,2 -> porcentagem de assistência tecnica no ano 5
                'meses_inicioConstrucao' => 4, //integer -> quantidade de meses antes do lancamento para execucao de plantao de vendas
                'porcentagem_ConstrucaoStand' => 0.025, //decimal 5,2 -> porcentagem de construção padrão sobre o VGV do projeto
                'gastos_mensaisStand' => 0.01, //decimal 5,4 -> gastos mensais padrão do plantao de vendas sobre o VGV do projeto em percentual
                'comissao_house' => 0.03, //decimal 5,2 -> comissao de house sobre o valor do imóvel em percentual
                'porcentagem_comissaoHouse' => 0.50, //decimal 5,2 -> porcentagem de comissao destinada para house
                'porcentagem_comissaoImobs' => 0.50, //decimal 5,2 -> porcentagem de comissao destinada para imobiliarias
                'pagto_comissaoNaVenda' => 0.50, //decimal 5,2 -> porcentagem de comissao pagas na venda sobre o valor do imóvel em percentual
                'marketing_antesLancamento' => 0.25, //decimal 5,2 -> porcentagem de marketing antes do lançamento do projeto, calculado sobre o valor total do marketing
                'marketing_lancamento' => 0.75, //decimal 5,2 -> porcentagem de marketing após o lançamento do projeto
                'pj_carenciaPosObra' => 6, //integer
                'pj_qtdeParcelasPosCarencia' => 18, //integer
            ]
        );
    }
}
