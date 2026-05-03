<?php

namespace App\Services\Tenant\Viabilidade\v1;

use App\Enums\PerfilFinanciamento;

/**
 * Encapsula todo o estado mutável produzido durante um cálculo de fluxo.
 *
 * Antes existia como propriedades privadas espalhadas no service, causando
 * acúmulo indevido de estado entre chamadas consecutivas na mesma instância.
 * Agora cada chamada a gerarFluxoMensal() recebe um contexto limpo.
 */
final class ViabilidadeFluxoContext
{
    public PerfilFinanciamento $perfil = PerfilFinanciamento::CEF;

    /** @var array<string, array<string, float>> Cache de recursos próprios por mês */
    public array $recursosProprios = [];

    /** @var array<string, float> Unidades vendidas por mês (Y-m → float) */
    public array $vendasPorMes = [];

    /** Acumulador de vendas durante iteração de receitas */
    public float $vendasAcumuladas = 0.0;

    /** Valor total a ser distribuído via medição de obra (CEF) */
    public float $valorMedicaoTotal = 0.0;

    /** Acumulado já recebido via medição de obra */
    public float $medicaoObraAcumulada = 0.0;

    /** Percentual acumulado da curva S de obra */
    public float $curvaObraAcumulada = 0.0;

    /** Último mês de obra processado (evita duplicar o acumulado) */
    public int $mesObraAtual = 0;

    /** Demanda mínima CEF somada de todos os produtos */
    public float $demandaMinima = 0.0;

    /** Indica se a demanda mínima CEF já foi atingida */
    public bool $demandaAtingida = false;

    /** Mês em que a demanda mínima CEF foi atingida (formato Y-m) */
    public ?string $mesDemandaAtingida = null;

    /** Indica se a Taxa de Contratação já foi paga (1x no 1º mês de lançamento) */
    public bool $txContratacaoPaga = false;

    public bool $bonusEquipeComercialPago = false;

    public float $comissaoDesligamentoAcumulada = 0.0;

    public bool $comissaoDesligamentoAcumuladaPaga = false;

    public float $contratosCefAcumulados = 0.0;

    public float $produtosCefAcumulados = 0.0;

    public bool $custosCefAcumuladosPagos = false;

    public float $parceriaVgvTotal = 0.0;

    public float $parceriaVgvPago = 0.0;

    /**
     * Parcelas atrasadas para recuperação parcial de inadimplência.
     *
     * Estrutura: [mesDestino => valorAcumulado]
     * Quando inadimplencia% das receitas atrasa, o valor é movido para
     * atrasoMeses à frente neste cache.
     *
     * @var array<string, float>
     */
    public array $parcelasAtrasadas = [];
}
