<?php

declare(strict_types=1);

namespace App\Services\Tenant\Viabilidade;

final class CarteiraPropriaStrategy
{
    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function calculate(array $parameters): array
    {
        $lots = (float) $parameters['quantidade_lotes'];
        $price = (float) $parameters['preco_lote'];
        $launch = (int) $parameters['mes_lancamento'];
        $delivery = (int) $parameters['mes_entrega'];
        $term = (int) $parameters['prazo_parcelas_meses'];
        $entryRate = (float) ($parameters['entrada_percentual'] ?? 0.20);
        $incc = (float) ($parameters['incc_mensal'] ?? 0.0054);
        $tr = (float) ($parameters['tr_mensal'] ?? 0.0015);
        $interest = (float) ($parameters['juros_pos_entrega_mensal'] ?? 0.01);
        $salesCurve = $parameters['curva_vendas_percentual'];
        $horizon = $delivery + $term;
        $cash = 0.0;
        $flow = [];
        $contracts = [];

        for ($month = 0; $month <= $horizon; $month++) {
            $sold = (float) ($salesCurve[$month - $launch] ?? 0.0) * $lots;
            $entry = $sold * $price * $entryRate;
            if ($sold > 0) {
                $contracts[] = ['month' => $month, 'principal' => $sold * $price * (1 - $entryRate)];
            }
            $principal = 0.0;
            $correction = 0.0;
            $financialInterest = 0.0;
            foreach ($contracts as $contract) {
                $elapsed = $month - $contract['month'];
                if ($elapsed < 1 || $elapsed > $term) {
                    continue;
                }
                $amortization = $contract['principal'] / $term;
                $balance = max(0.0, $contract['principal'] - ($amortization * ($elapsed - 1)));
                $index = $month <= $delivery ? $incc : $tr;
                $correction += $balance * $index;
                $financialInterest += $month > $delivery ? ($balance + ($balance * $index)) * $interest : 0.0;
                $principal += min($amortization, $balance);
            }
            $received = $entry + $principal + $correction + $financialInterest;
            $cash += $received;
            $flow[sprintf('mes_%03d', $month)] = [
                'receitas' => [
                    'recursos_proprios' => ['recurso_proprio' => $principal, 'recursos_atrasados' => 0.0, 'juros' => $financialInterest, 'correcoes' => $correction, 'total_recursos_proprios' => $received],
                    'recebimento_terreno' => ['recebimento_total_terreno' => $entry],
                    'medicao_obra' => ['recebimento_total_medicao' => 0.0],
                    'total' => $received,
                ],
                'despesas' => ['total' => 0.0],
                'saldo_mes' => $received,
                'saldo_acumulado_mes' => $cash,
                'unidades_vendidas' => $sold,
            ];
        }

        $vgv = $lots * $price;

        return [
            'motor' => 'carteira_propria',
            'fluxo_mensal' => $flow,
            'fluxo_mensal_financeiro' => [],
            'vgv' => $vgv,
            'vgv_total' => $vgv,
            'receita_total_vendas' => $vgv,
            'receita_bruta' => $cash,
            'receita_liquida' => $cash,
            'totais' => ['receita' => $cash, 'custo_direto' => 0.0, 'impostos' => 0.0, 'custos_operacionais' => 0.0, 'custos_financeiros' => 0.0, 'lucro' => $cash],
            'indicadores' => ['vgv_total' => $vgv, 'lucro_liquido' => $cash, 'margem_liquida_percentual' => $vgv > 0 ? ($cash / $vgv) * 100 : 0.0],
            'parametros_utilizados' => $parameters,
        ];
    }
}
