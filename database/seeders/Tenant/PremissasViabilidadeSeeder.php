<?php

namespace Database\Seeders\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Models\Tenant\PremissasViabilidade;
use Illuminate\Database\Seeder;

class PremissasViabilidadeSeeder extends Seeder
{
    public function run(): void
    {
        $jaExisteCef = PremissasViabilidade::ativo()->porPerfil('cef')->exists();
        $jaExisteProprio = PremissasViabilidade::ativo()->porPerfil('proprio')->exists();

        if ($jaExisteCef && $jaExisteProprio) {
            return;
        }

        $d = config('viabilidade.defaults', []);
        $p = config('viabilidade.prazos', []);
        $hoje = now()->toDateString();

        $valoresBase = [
            'ativo' => true,
            'vigente_em' => $hoje,
            'versao' => 1,

            'pis_cofins'                     => $d['pis_cofins'] ?? 4.0,
            'iss'                            => $d['iss'] ?? 0.0,
            'outros_impostos'                => $d['outros_impostos'] ?? 0.5,
            'comissao'                       => $d['comissao'] ?? 0.0,
            'parceria_vgv'                   => $d['parceria_vgv'] ?? 0.0,
            'infra_nao_incidente'            => $d['infra_nao_incidente'] ?? 1.0,
            'incorporacao'                   => $d['incorporacao'] ?? 1.0,
            'incorp_ri'                      => 30.0,
            'incorp_entrega'                 => 15.0,
            'incorp_ate_lancamento'          => 80.0,
            'area_comum'                     => $d['area_comum'] ?? 0.0,
            'contrapartidas'                 => $d['contrapartidas'] ?? 0.0,
            'canteiro_mensal'                => $d['canteiro_mensal'] ?? 85715.0,
            'mo_administrativa'              => $d['mo_administrativa'] ?? 62502.0,
            'seguros'                        => $d['seguros'] ?? 0.5,
            'assistencia_tecnica'            => $d['assistencia_tecnica'] ?? 1.0,
            'despesas_comerciais'            => $d['despesas_comerciais'] ?? 5.0,
            'stand_vendas'                   => $d['stand_vendas'] ?? 0.0,
            'mobilia_decoracao'              => $d['mobilia_decoracao'] ?? 90000.0,
            'ajuda_custo_gerente'            => $d['ajuda_custo_gerente'] ?? 5000.0,
            'ajuda_custo_gerente_regional'   => $d['ajuda_custo_gerente_regional'] ?? 2733.0,
            'reembolso_logistica'            => $d['reembolso_logistica'] ?? 5000.0,
            'bonus_cca'                      => $d['bonus_cca'] ?? 350.0,
            'bonus_gerente'                  => $d['bonus_gerente'] ?? 0.3,
            'bonus_gerente_regional'         => $d['bonus_gerente_regional'] ?? 0.12,
            'bonus_credito'                  => $d['bonus_credito'] ?? 0.05,
            'bonus_gestor_comercial'         => $d['bonus_gestor_comercial'] ?? 0.05,
            'pagamento_comissao_desligamento' => $d['pagamento_comissao_desligamento'] ?? 50.0,
            'parcelamento_comissao_terreno'  => $d['parcelamento_comissao_terreno'] ?? 18,
            'parcelamento_comissao_meses'    => $d['parcelamento_comissao_meses'] ?? 18,
            'marketing'                      => $d['marketing'] ?? 1.0,
            'marketing_inicio_antes_lancamento' => 3,
            'itbi_iptu'                      => $d['itbi_iptu'] ?? 1.1,
            'registro'                       => $d['registro'] ?? 2500.0,
            'custo_contratacao_cef'          => $d['custo_contratacao_cef'] ?? 24000.0,
            'custo_medicao_cef'              => $d['custo_medicao_cef'] ?? 2000.0,
            'contratos_cef'                  => $d['contratos_cef'] ?? 300.0,
            'produtos_cef'                   => $d['produtos_cef'] ?? 0.5,
            'outras_despesas_financeiras'    => $d['outras_despesas_financeiras'] ?? 0.3,
            'despesas_onerosas_bancos'       => $d['despesas_onerosas_bancos'] ?? 10.0,
            'prazo_obra'                     => $d['prazo_obra'] ?? 36,
            'compra_terreno'                 => $d['compra_terreno'] ?? 0.0,
            'porcentagem_lote_proprietario'  => $d['porcentagem_lote_proprietario'] ?? 10.0,
            'taxa_juros_pj'                  => $d['taxa_juros_pj'] ?? 10.5,
            'carencia_pj_meses'              => 6,
            'amortizacao_pj_parcelas'        => 18,
            'percentual_antecipacao_pj'      => $d['percentual_antecipacao_pj'] ?? 10.0,
            'aporte_adicional_mensal'        => $d['aporte_adicional_mensal'] ?? 0.0,
            'devolucao_aporte_percentual'    => $d['devolucao_aporte_percentual'] ?? 20.0,
            'distribuicao_lucros_percentual_obra' => $d['distribuicao_lucros_percentual_obra'] ?? 100.0,
            'taxa_exposicao_aplicada'        => $d['taxa_exposicao_aplicada'] ?? 12.5,
            'avaliacao_lotes_cef'            => $d['avaliacao_lotes_cef'] ?? null,
            'inadimplencia'                  => $d['inadimplencia'] ?? 0.10,
            'atraso_meses'                   => $d['atraso_meses'] ?? 2,
            'taxa_perda'                     => $d['taxa_perda'] ?? 0.02,

            'meses_incorporacao'             => $p['meses_incorporacao'] ?? 18,
            'meses_lancamento'               => $p['meses_lancamento'] ?? 6,
            'meses_entrega'                  => $p['meses_entrega'] ?? 1,
            'meses_pos_obra'                 => $p['meses_pos_obra'] ?? 60,
            'obra_ate_lancamento'            => 1.0,
        ];

        if (! $jaExisteCef) {
            PremissasViabilidade::create(array_merge($valoresBase, [
                'nome' => 'Padrão CEF',
                'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            ]));
        }

        if (! $jaExisteProprio) {
            PremissasViabilidade::create(array_merge($valoresBase, [
                'nome' => 'Padrão Próprio',
                'perfil_financiamento' => PerfilFinanciamento::PROPRIO->value,
                'custo_contratacao_cef' => 24000.0,
                'custo_medicao_cef' => 2000.0,
                'contratos_cef' => 0.0,
                'produtos_cef' => 0.0,
                'inadimplencia' => 0.15,
                'atraso_meses' => 3,
                'taxa_perda' => 0.05,
            ]));
        }
    }
}
