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
        protected readonly PremissasViabilidadeService $premissasService,
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
            $resultado = $this->fluxoMensalCalculator->calcular($terreno, $params, $customProdutos);

            $this->salvarSnapshot($viabilidade, $resultado);

            return $resultado;
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
        $perfilValue = $v?->perfil_financiamento;
        $perfilStr = $perfilValue instanceof PerfilFinanciamento
            ? $perfilValue->value
            : 'cef';

        $defaults = $this->premissasService->resolverDefaults($perfilStr);

        return [
            'percentualImpostos' => (($v->pis_cofins ?? $defaults['pis_cofins']) + ($v->iss ?? $defaults['iss']) + ($v->outros_impostos ?? $defaults['outros_impostos'])) / 100,
            'percentualPisCofins' => ($v->pis_cofins ?? $defaults['pis_cofins']) / 100,
            'percentualIss' => ($v->iss ?? $defaults['iss']) / 100,
            'percentualOutrosImpostos' => ($v->outros_impostos ?? $defaults['outros_impostos']) / 100,
            'percentualComissao' => ($v->comissao ?? $defaults['comissao']) / 100,
            'parceriaVgv' => ($v->parceria_vgv ?? $defaults['parceria_vgv']) / 100,
            'infraNaoIncidente' => ($v->infra_nao_incidente ?? $defaults['infra_nao_incidente']) / 100,
            'porcentagemLoteProprietario' => ($v->porcentagem_lote_proprietario ?? $defaults['porcentagem_lote_proprietario']) / 100,
            'percentualIncorporacao' => ($v->incorporacao ?? $defaults['incorporacao']) / 100,
            'custoAreaComum' => $v->area_comum ?? $defaults['area_comum'],
            'percentualContrapartidas' => ($v->contrapartidas ?? $defaults['contrapartidas']) / 100,
            'canteiroMensal' => $v->canteiro_mensal ?? $defaults['canteiro_mensal'],
            'moAdministrativa' => $v->mo_administrativa ?? $defaults['mo_administrativa'],
            'percentualSeguros' => ($v->seguros ?? $defaults['seguros']) / 100,
            'percentualAssistenciaTecnica' => ($v->assistencia_tecnica ?? $defaults['assistencia_tecnica']) / 100,
            'percentualDespesasComerciais' => ($v->despesas_comerciais ?? $defaults['despesas_comerciais']) / 100,
            'standVendas' => $v->stand_vendas ?? $defaults['stand_vendas'],
            'mobiliaDecoracao' => $v->mobilia_decoracao ?? $defaults['mobilia_decoracao'],
            'ajudaCustoGerente' => $v->ajuda_custo_gerente ?? $defaults['ajuda_custo_gerente'],
            'ajudaCustoGerenteRegional' => $v->ajuda_custo_gerente_regional ?? $defaults['ajuda_custo_gerente_regional'],
            'reembolsoLogistica' => $v->reembolso_logistica ?? $defaults['reembolso_logistica'],
            'bonusCca' => $v->bonus_cca ?? $defaults['bonus_cca'],
            'bonusGerente' => ($v->bonus_gerente ?? $defaults['bonus_gerente']) / 100,
            'bonusGerenteRegional' => ($v->bonus_gerente_regional ?? $defaults['bonus_gerente_regional']) / 100,
            'bonusCredito' => ($v->bonus_credito ?? $defaults['bonus_credito']) / 100,
            'bonusGestorComercial' => ($v->bonus_gestor_comercial ?? $defaults['bonus_gestor_comercial']) / 100,
            'pagamentoComissaoDesligamento' => ($v->pagamento_comissao_desligamento ?? $defaults['pagamento_comissao_desligamento']) / 100,
            'parcelamentoComissaoMeses' => (int) ($v->parcelamento_comissao_meses ?? $defaults['parcelamento_comissao_meses']),
            'parcelamentoComissaoTerreno' => (int) ($defaults['parcelamento_comissao_terreno'] ?? 1),
            'percentualMarketing' => ($v->marketing ?? $defaults['marketing']) / 100,
            'custoItbiIptu' => ($v->itbi_iptu ?? $defaults['itbi_iptu']) / 100,
            'custoRegistro' => $v->registro ?? $defaults['registro'],
            'custoContratacaoCef' => $v->custo_contratacao_cef ?? $defaults['custo_contratacao_cef'],
            'custoMedicaoCef' => $v->custo_medicao_cef ?? $defaults['custo_medicao_cef'],
            'custoContratosCef' => $v->contratos_cef ?? $defaults['contratos_cef'],
            'percentualProdutosCef' => ($v->produtos_cef ?? $defaults['produtos_cef']) / 100,
            'percentualOutrasDespesasFinanceiras' => ($v->outras_despesas_financeiras ?? $defaults['outras_despesas_financeiras']) / 100,
            'mesesObra' => (int) ($v->prazo_obra ?? $defaults['prazo_obra']),
            'mesesIncorporacao' => (int) ($v->prazo_incorporacao ?? $defaults['meses_incorporacao']),
            'mesesLancamento' => (int) ($v->prazo_lancamento ?? $defaults['meses_lancamento']),
            'mesesEntrega' => $defaults['meses_entrega'],
            'mesesPosObra' => $defaults['meses_pos_obra'],
            'compraTerreno' => $v->compra_terreno ?? $defaults['compra_terreno'],
            'taxaJurosPj' => ($defaults['taxa_juros_pj']) / 100,
            'carenciaPjMeses' => (int) $defaults['carencia_pj_meses'],
            'amortizacaoPjParcelas' => (int) $defaults['amortizacao_pj_parcelas'],
            'percentualAntecipacaoPj' => ($v->percentual_antecipacao_pj ?? $defaults['percentual_antecipacao_pj']) / 100,
            'aporteAdicionalMensal' => $v->aporte_adicional_mensal ?? $defaults['aporte_adicional_mensal'],
            'devolucaoAportePercentual' => ($v->devolucao_aporte_percentual ?? $defaults['devolucao_aporte_percentual']) / 100,
            'distribuicaoLucrosPercentualObra' => ($v->distribuicao_lucros_percentual_obra ?? $defaults['distribuicao_lucros_percentual_obra']) / 100,
            'taxaExposicaoAplicada' => ($v->taxa_exposicao_aplicada ?? $defaults['taxa_exposicao_aplicada']) / 100,
            'incorporacaoRi' => $defaults['incorp_ri'] / 100,
            'incorporacaoEntrega' => $defaults['incorp_entrega'] / 100,
            'incorporacaoAteLancamento' => $defaults['incorp_ate_lancamento'] / 100,
            'obraAteLancamento' => $defaults['obra_ate_lancamento'] / 100,
            'marketingInicioAntesLancamento' => (int) $defaults['marketing_inicio_antes_lancamento'],
            'custoMedicaoContratacao' => $defaults['custo_contratacao_cef'] ?? 0,
            'perfilFinanciamento' => PerfilFinanciamento::tryFrom((string) $perfilStr) ?? PerfilFinanciamento::CEF,
            'dataLancamento' => $v->data_lancamento
                ? Carbon::parse($v->data_lancamento)
                : $defaults['data_lancamento_padrao'],
            'inadimplencia' => (float) $defaults['inadimplencia'],
            'atrasoMeses' => (int) $defaults['atraso_meses'],
            'taxaPerda' => (float) $defaults['taxa_perda'],
        ];
    }

    /**
     * Salva um snapshot das premissas utilizadas no cálculo na viabilidade.
     * Inclui o resultado do cálculo para rastreabilidade completa.
     */
    private function salvarSnapshot(Viabilidade $viabilidade, array $resultado): void
    {
        $viabilidade->premissas_snapshot = [
            'calculado_em' => now()->toDateTimeString(),
            'parametros' => $resultado['parametros_utilizados'] ?? [],
            'indicadores' => $resultado['indicadores'] ?? [],
            'vgv' => $resultado['vgv'] ?? null,
            'total_unidades' => $resultado['totalUnidades'] ?? null,
        ];

        $viabilidade->saveQuietly();
    }

}
