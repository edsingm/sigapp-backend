<?php

namespace App\Services\Tenant\Viabilidade\v1;

use App\Enums\PerfilFinanciamento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use App\Services\Tenant\Viabilidade\v1\Calculos\DespesasCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\DreCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\FluxoMensalCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\IndicadoresCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\PocCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\ProdutosProcessor;
use App\Services\Tenant\Viabilidade\v1\Calculos\ReceitasCalculator;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * ViabilidadeUnificadoService — Motor de cálculo financeiro de viabilidade imobiliária.
 *
 * Quatro responsabilidades públicas:
 *  1. gerarFluxoMensal()  — orquestra o pipeline completo e devolve fluxo de caixa
 *  2. calcularReceitas()  — receitas de um mês específico
 *  3. calcularDespesas()  — despesas de um mês específico
 *  4. calcularDre()       — DRE consolidada do projeto
 *
 * O estado mutável do cálculo é isolado em ViabilidadeFluxoContext, garantindo
 * que chamadas consecutivas na mesma instância não acumulem dados entre si.
 */
class ViabilidadeUnificadoService
{
    public function __construct(
        protected readonly CurvaService $curvaService,
        protected readonly ImpostosService $impostosService,
        protected readonly DreCalculator $dreCalculator,
        protected readonly ReceitasCalculator $receitasCalculator,
        protected readonly DespesasCalculator $despesasCalculator,
        protected readonly IndicadoresCalculator $indicadoresCalculator,
        protected readonly PocCalculator $pocCalculator,
        protected readonly ProdutosProcessor $produtosProcessor,
        protected readonly FluxoMensalCalculator $fluxoMensalCalculator,
    ) {}

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 1: GERAR FLUXO MENSAL
     * ═══════════════════════════════════════════════════════════════════════
     * Orquestra todo o cálculo e retorna fluxo de caixa completo.
     * Cada chamada cria um ViabilidadeFluxoContext limpo, garantindo
     * que o estado não vaze entre invocações consecutivas.
     */
    public function gerarFluxoMensal(
        int $terrenoId,
        Viabilidade|int|null $viabilidadeRef = null,
        ?array $customProdutos = null
    ): array{
        try {
            $terreno = $this->buscarTerreno($terrenoId);
            $viabilidade = $this->buscarViabilidade($terrenoId, $viabilidadeRef);
            $params = $this->montarParametros($viabilidade);
            return $this->fluxoMensalCalculator->calcular($terreno, $params, $customProdutos);
        } catch (Exception $e) {
            Log::error('Erro ao gerar fluxo mensal: '.$e->getMessage(), [
                'terrenoId' => $terrenoId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Erro ao gerar fluxo mensal: '.$e->getMessage());
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 2: CALCULAR RECEITAS
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula todas as receitas de um mês específico.
     *
     * Quando chamado diretamente (fora do pipeline de gerarFluxoMensal) recebe
     * um contexto opcional — sem contexto, os caches CEF são zero, logo RT e MO
     * retornam 0, o que é o comportamento correto para testes unitários isolados.
     */
    public function calcularReceitas(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params,
        ?ViabilidadeFluxoContext $ctx = null
    ): array {
        return $this->receitasCalculator->calcular($mes, $dadosProdutos, $datas, $params, $ctx);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 3: CALCULAR DESPESAS
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula todas as despesas de um mês específico.
     */
    public function calcularDespesas(
        string $mes,
        array $receitas,
        array $dadosProdutos,
        array $datas,
        array $params,
        ?ViabilidadeFluxoContext $ctx = null
    ): array {
        return $this->despesasCalculator->calcular($mes, $receitas, $dadosProdutos, $datas, $params, $ctx);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 4: CALCULAR DRE
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula a DRE consolidada.
     */
    public function calcularDre(array $fluxo, array $dadosProdutos, array $params): array
    {
        return $this->dreCalculator->calcular($fluxo, $dadosProdutos, $params);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * HELPERS 
     * ═══════════════════════════════════════════════════════════════════════
     */


    private function buscarTerreno(int $terrenoId): Terreno
    {
        return Terreno::select(['id', 'nome', 'area_calculada', 'data_contrato'])
            ->with([
                'terrenoProdutos' => fn ($q) => $q->select(['terreno_id', 'produto_id', 'unidades', 'valor', 'permuta', 'id', 'pgto_por_lote']),
                'terrenoProdutos.produto' => fn ($q) => $q->select([
                    'id',
                    'name',
                    'private_area',
                    'm2_cost',
                    'infra_cost',
                    'sinal',
                    'parcela_obra',
                    'parcela_posChave',
                    'qtde_parcelas_posChave',
                    'juros_mensalSinal',
                    'juros_mensalObra',
                    'juros_mensalPosChave',
                    'correcao_anualSinal',
                    'correcao_anualObra',
                    'correcao_anualPosChave',
                    'imposto_outros',
                    'imposto_tributos',
                    'imposto_iss',
                    'demanda_minCef',
                    'curva_vendas',
                    'avaliacao_lotesCef',
                    'baloes_anuais',
                    'balao_entrega_modo',
                    'gastos_mensaisStand',
                    'comissao_house',
                    'porcentagem_comissaoHouse',
                    'porcentagem_comissaoImobs',
                    'pagto_comissaoNaVenda',
                    'marketing_lancamento',
                    'marketing_antesLancamento',
                    'custo_contratacaoCef',
                    'pj_taxaJuros',
                    'pj_carenciaPosObra',
                    'pj_qtdeParcelasPosCarencia',
                    'assist_tecnica1',
                    'assist_tecnica2',
                    'assist_tecnica3',
                    'assist_tecnica4',
                    'assist_tecnica5',
                    'incorp_ri',
                    'incorp_entrega',
                    'incorp_ateLancamento',
                ]),
            ])
            ->findOrFail($terrenoId);
    }

    private function buscarViabilidade(int $terrenoId, $viabilidadeRef): Viabilidade
    {
        if ($viabilidadeRef instanceof Viabilidade) {
            return $viabilidadeRef;
        }
        if (is_numeric($viabilidadeRef)) {
            return Viabilidade::findOrFail($viabilidadeRef);
        }

        return Viabilidade::where('terreno_id', $terrenoId)->latest()->first()
            ?? new Viabilidade(['terreno_id' => $terrenoId]);
    }

    private function montarParametros(?Viabilidade $v): array
    {
        $d = config('viabilidade.defaults');
        $p = config('viabilidade.prazos');

        $perfilValue = $v?->perfil_financiamento;
        $perfilStr = $perfilValue instanceof PerfilFinanciamento
            ? $perfilValue->value
            : $d['perfil_financiamento'];

        return [
            'percentualImpostos' => (($v->pis_cofins ?? $d['pis_cofins']) + ($v->iss ?? $d['iss']) + ($v->outros_impostos ?? $d['outros_impostos'])) / 100,
            'percentualPisCofins' => ($v->pis_cofins ?? $d['pis_cofins']) / 100,
            'percentualIss' => ($v->iss ?? $d['iss']) / 100,
            'percentualOutrosImpostos' => ($v->outros_impostos ?? $d['outros_impostos']) / 100,
            'percentualComissao' => ($v->comissao ?? $d['comissao']) / 100,
            'parceriaVgv' => ($v->parceria_vgv ?? $d['parceria_vgv']) / 100,
            'infraNaoIncidente' => ($v->infra_nao_incidente ?? $d['infra_nao_incidente']) / 100,
            'porcentagemLoteProprietario' => ($v->porcentagem_lote_proprietario ?? $d['porcentagem_lote_proprietario'] ?? 10) / 100,
            'percentualIncorporacao' => ($v->incorporacao ?? $d['incorporacao']) / 100,
            'custoAreaComum' => $v->area_comum ?? $d['area_comum'],
            'percentualContrapartidas' => ($v->contrapartidas ?? $d['contrapartidas']) / 100,
            'canteiroMensal' => $v->canteiro_mensal ?? $d['canteiro_mensal'],
            'moAdministrativa' => $v->mo_administrativa ?? $d['mo_administrativa'],
            'percentualSeguros' => ($v->seguros ?? $d['seguros']) / 100,
            'percentualAssistenciaTecnica' => ($v->assistencia_tecnica ?? $d['assistencia_tecnica']) / 100,
            'percentualDespesasComerciais' => ($v->despesas_comerciais ?? $d['despesas_comerciais']) / 100,
            'standVendas' => $v->stand_vendas ?? $d['stand_vendas'],
            'mobiliaDecoracao' => $v->mobilia_decoracao ?? $d['mobilia_decoracao'],
            'ajudaCustoGerente' => $v->ajuda_custo_gerente ?? $d['ajuda_custo_gerente'],
            'ajudaCustoGerenteRegional' => $v->ajuda_custo_gerente_regional ?? $d['ajuda_custo_gerente_regional'],
            'reembolsoLogistica' => $v->reembolso_logistica ?? $d['reembolso_logistica'],
            'bonusCca' => $v->bonus_cca ?? $d['bonus_cca'],
            'bonusGerente' => ($v->bonus_gerente ?? $d['bonus_gerente']) / 100,
            'bonusGerenteRegional' => ($v->bonus_gerente_regional ?? $d['bonus_gerente_regional']) / 100,
            'bonusCredito' => ($v->bonus_credito ?? $d['bonus_credito']) / 100,
            'bonusGestorComercial' => ($v->bonus_gestor_comercial ?? $d['bonus_gestor_comercial']) / 100,
            'pagamentoComissaoDesligamento' => ($v->pagamento_comissao_desligamento ?? $d['pagamento_comissao_desligamento']) / 100,
            'parcelamentoComissaoMeses' => (int) ($v->parcelamento_comissao_meses ?? $d['parcelamento_comissao_meses']),
            'percentualMarketing' => ($v->marketing ?? $d['marketing']) / 100,
            'custoItbiIptu' => ($v->itbi_iptu ?? $d['itbi_iptu']) / 100,
            'custoRegistro' => $v->registro ?? $d['registro'],
            'custoContratacaoCef' => $v->custo_contratacao_cef ?? $d['custo_contratacao_cef'] ?? 0,
            'custoMedicaoCef' => $v->custo_medicao_cef ?? $d['custo_medicao_cef'] ?? 0,
            'custoContratosCef' => $v->contratos_cef ?? $d['contratos_cef'],
            'percentualProdutosCef' => ($v->produtos_cef ?? $d['produtos_cef']) / 100,
            'percentualOutrasDespesasFinanceiras' => ($v->outras_despesas_financeiras ?? $d['outras_despesas_financeiras']) / 100,
            'mesesObra' => (int) ($v->prazo_obra ?? $d['prazo_obra']),
            'mesesIncorporacao' => (int) ($v->prazo_incorporacao ?? $p['meses_incorporacao']),
            'mesesLancamento' => (int) ($v->prazo_lancamento ?? $p['meses_lancamento']),
            'mesesEntrega' => $p['meses_entrega'],
            'mesesPosObra' => $p['meses_pos_obra'],
            'variavelCorrecao' => $p['variavel_correcao'],
            'compraTerreno' => $v->compra_terreno ?? 0,
            'percentualAntecipacaoPj' => ($v->percentual_antecipacao_pj ?? $d['percentual_antecipacao_pj']) / 100,
            'aporteAdicionalMensal' => $v->aporte_adicional_mensal ?? $d['aporte_adicional_mensal'],
            'devolucaoAportePercentual' => ($v->devolucao_aporte_percentual ?? $d['devolucao_aporte_percentual']) / 100,
            'distribuicaoLucrosPercentualObra' => ($v->distribuicao_lucros_percentual_obra ?? $d['distribuicao_lucros_percentual_obra']) / 100,
            'taxaExposicaoAplicada' => ($v->taxa_exposicao_aplicada ?? $d['taxa_exposicao_aplicada']) / 100,
            'perfilFinanciamento' => PerfilFinanciamento::tryFrom((string) $perfilStr) ?? PerfilFinanciamento::CEF,
            'dataLancamento' => $v->data_lancamento 
                ? Carbon::parse($v->data_lancamento) 
                : Carbon::now()->addYears(2),
            'inadimplencia' => (float) ($d['inadimplencia'] ?? 0.10),
            'atrasoMeses' => (int) ($d['atraso_meses'] ?? 2),
            'taxaPerda' => (float) ($d['taxa_perda'] ?? 0.02),
        ];
    }

}
