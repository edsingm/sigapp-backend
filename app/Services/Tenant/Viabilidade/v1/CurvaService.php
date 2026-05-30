<?php

namespace App\Services\Tenant\Viabilidade\v1;

/**
 * CurvaService - Centraliza todas as curvas do sistema de viabilidade
 * 
 * Responsabilidades:
 * - Curva S de desembolso de obra
 * - Curvas de vendas por tipo de produto
 * - Interpolação e normalização
 */
class CurvaService
{
    /**
     * Curvas de desembolso de obra por período de construção (Curva S)
     * Valores em percentual do custo total de obra.
     * Fonte: Dados históricos do projeto legado.
     *
     * @var array<int, list<float>>
     */
    private array $curvasObra = [
        18 => [1.5, 2.0, 3.0, 4.5, 5.5, 6.5, 7.5, 8.5, 9.0, 9.0, 8.5, 7.5, 6.5, 5.5, 4.5, 4.0, 3.5, 2.5],
        20 => [0.8, 1.3, 1.5, 3.5, 4.5, 4.8, 6.0, 6.5, 7.0, 8.0, 8.0, 9.0, 8.0, 7.0, 6.5, 4.5, 3.0, 1.5, 1.2],
        24 => [0.8, 1.3, 1.5, 3.5, 4.0, 4.0, 4.5, 4.5, 5.5, 6.0, 7.0, 7.0, 7.5, 6.5, 6.0, 5.0, 5.0, 5.0, 4.5, 4.5, 2.5, 2.0, 1.5, 0.5],
        30 => [2.0, 2.5, 2.9, 3.3, 3.5, 4.5, 5.0, 6.0, 7.0, 7.0, 5.5, 5.5, 4.0, 4.0, 4.0, 4.0, 4.0, 3.0, 3.0, 3.0, 2.8, 2.5, 2.5, 2.5, 2.0, 1.0, 1.5, 0.5, 0.5, 0.5],
        36 => [0.99, 1.188, 1.386, 1.584, 1.782, 1.98, 2.178, 2.376, 2.574, 2.772, 2.97, 3.168, 3.366, 3.564, 3.762, 3.96, 4.158, 4.257, 4.257, 4.257, 4.257, 3.96, 3.762, 3.564, 3.366, 3.168, 2.97, 2.772, 2.574, 2.376, 2.178, 1.98, 1.782, 1.584, 1.386, 0.792]
    ];

    /**
     * Retorna o percentual de custo de obra para um mês específico usando Curva S
     * 
     * @param int $mesesTotal Duração total da obra em meses
     * @param int $mesAtual Mês atual da obra (1 a N)
     * @return float Percentual do custo total para o mês
     */
    public function getPercentualCustoObra(int $mesesTotal, int $mesAtual): float
    {
        $curva = $this->getCurvaObraParaPrazo($mesesTotal);

        if ($mesesTotal === $this->getPrazoMaisProximo($mesesTotal)) {
            $indice = $mesAtual - 1;
            return $curva[$indice] ?? 0;
        }

        // Interpolação para prazos diferentes
        $progresso = $mesAtual / $mesesTotal;
        $indiceVirtual = (int) round($progresso * count($curva)) - 1;
        $indiceVirtual = max(0, min($indiceVirtual, count($curva) - 1));

        return $curva[$indiceVirtual] ?? 0;
    }

    /**
     * Retorna a curva de obra completa normalizada para um prazo específico
     * 
     * @param int $mesesTotal Duração total da obra
     * @return array Curva normalizada para 100%
     */
    /**
     * @return list<float>
     */
    public function getCurvaObraParaPrazo(int $mesesTotal): array
    {
        $prazoMaisProximo = $this->getPrazoMaisProximo($mesesTotal);
        $curva = $this->curvasObra[$prazoMaisProximo];

        return $this->normalizarCurva($curva);
    }

    /**
     * @return list<float>
     */
    public function getCurvaObraBaseParaPrazo(int $mesesTotal): array
    {
        $prazoMaisProximo = $this->getPrazoMaisProximo($mesesTotal);
        $curvaBase = $this->curvasObra[$prazoMaisProximo];

        if ($mesesTotal === $prazoMaisProximo) {
            return $curvaBase;
        }

        $somaBase = array_sum($curvaBase);
        $curvaInterpoladaNormalizada = $this->interpolarCurva($curvaBase, $mesesTotal);
        if ($somaBase <= 0.0) {
            return $curvaInterpoladaNormalizada;
        }

        $fator = $somaBase / 100.0;

        return array_map(
            static fn (float $valor): float => $valor * $fator,
            $curvaInterpoladaNormalizada,
        );
    }

    /**
     * @return array<int, float>
     */
    public function getCurvaFinanceiraMedicaoParaPrazo(int $mesesTotal, float $obraAteLancamento = 0.0): array
    {
        unset($obraAteLancamento);

        $curvaObra = array_map(
            static fn (float $percentual): float => round($percentual, 1),
            $this->getCurvaObraParaPrazo($mesesTotal),
        );
        $curvaFinanceira = array_fill(0, $mesesTotal + 5, 0.0);
        $acumulado = 0.0;

        foreach ($curvaObra as $indice => $percentualFinanceiro) {
            if ($percentualFinanceiro <= 0.0) {
                continue;
            }

            $novoAcumulado = round($acumulado + $percentualFinanceiro, 1);
            if ($novoAcumulado > 95.0) {
                break;
            }

            $curvaFinanceira[$indice] = $percentualFinanceiro;
            $acumulado = $novoAcumulado;
        }

        $saldoFinal = round(100.0 - $acumulado, 1);
        if ($saldoFinal > 0.0) {
            $primeiraParcela = round($saldoFinal * 0.55, 1);
            $segundaParcela = round($saldoFinal - $primeiraParcela, 1);

            $curvaFinanceira[$mesesTotal + 1] = $primeiraParcela;
            $curvaFinanceira[$mesesTotal + 4] = $segundaParcela;
        }

        return $curvaFinanceira;
    }

    /**
     * Encontra o prazo de curva mais próximo disponível
     */
    private function getPrazoMaisProximo(int $mesesTotal): int
    {
        $prazosDisponiveis = array_keys($this->curvasObra);
        $prazoMaisProximo = $prazosDisponiveis[0];
        $menorDiferenca = PHP_INT_MAX;

        foreach ($prazosDisponiveis as $prazo) {
            $diff = abs($prazo - $mesesTotal);
            if ($diff < $menorDiferenca) {
                $menorDiferenca = $diff;
                $prazoMaisProximo = $prazo;
            }
        }

        return $prazoMaisProximo;
    }

    /**
     * Normaliza uma curva para que a soma seja exatamente 100%
     */
    /**
     * @param  list<float>  $curva
     * @return list<float>
     */
    public function normalizarCurva(array $curva): array
    {
        $soma = array_sum($curva);

        if ($soma > 0 && abs($soma - 100) > 0.1) {
            $fator = 100 / $soma;
            $curva = array_map(static fn (float $val): float => $val * $fator, $curva);
        }

        return $curva;
    }

    /**
     * Distribui um valor total ao longo do tempo baseado em uma curva
     * 
     * @param float $total Valor total a distribuir
     * @param \Carbon\Carbon $dataInicio Data de início da distribuição
     * @param array $curva Curva de distribuição (percentuais)
     * @return array Array associativo [Y-m => valor]
     */
    /**
     * @param  list<float>  $curva
     * @return array<string, float>
     */
    public function distribuirPorCurva(float $total, \Carbon\Carbon $dataInicio, array $curva): array
    {
        $distribuicao = [];
        $dataAtual = $dataInicio->copy();

        foreach ($curva as $percentual) {
            if ($percentual <= 0) {
                $dataAtual->addMonth();
                continue;
            }

            $valorMes = $total * ($percentual / 100);
            $chaveMes = $dataAtual->format('Y-m');

            $distribuicao[$chaveMes] = ($distribuicao[$chaveMes] ?? 0) + $valorMes;
            $dataAtual->addMonth();
        }

        return $distribuicao;
    }

    /**
     * @param  array<array-key, mixed>|string|null  $valor
     * @return list<float>
     */
    public function extrairCurva(array|string|null $valor): array
    {
        if ($valor === null) {
            return [];
        }

        if (is_string($valor)) {
            $decoded = json_decode($valor, true);
            if (! is_array($decoded)) {
                return [];
            }
            $valor = $decoded;
        }

        $curva = [];
        foreach ($valor as $item) {
            if (! is_numeric($item)) {
                continue;
            }
            $numero = (float) $item;
            if ($numero < 0) {
                continue;
            }
            $curva[] = $numero;
        }

        return $curva;
    }

    /**
     * @param  list<float>  $curva
     * @return list<float>
     */
    public function ajustarCurva(array $curva, int $meses): array
    {
        $meses = max(0, $meses);
        if ($meses === 0) {
            return [];
        }

        if (count($curva) < $meses) {
            $curva = array_pad($curva, $meses, 0.0);
        } elseif (count($curva) > $meses) {
            $curva = array_slice($curva, 0, $meses);
        }

        return $this->normalizarCurva($curva);
    }

    /**
     * @param  list<float>  $curva
     * @return list<float>
     */
    public function interpolarCurva(array $curva, int $meses): array
    {
        $meses = max(0, $meses);
        $n = count($curva);

        if ($meses === 0 || $n === 0) {
            return [];
        }

        if ($meses === $n) {
            return $this->normalizarCurva($curva);
        }

        if ($meses === 1) {
            return [100.0];
        }

        if ($n === 1) {
            return $this->normalizarCurva(array_fill(0, $meses, (float) $curva[0]));
        }

        $resultado = [];
        for ($i = 0; $i < $meses; $i++) {
            $pos = ($i * ($n - 1)) / ($meses - 1);
            $left = (int) floor($pos);
            $right = (int) ceil($pos);
            $weight = $pos - $left;
            $vl = (float) ($curva[$left] ?? 0.0);
            $vr = (float) ($curva[$right] ?? $vl);
            $resultado[] = ($vl * (1 - $weight)) + ($vr * $weight);
        }

        return $this->normalizarCurva($resultado);
    }

    /**
     * @param  array<array-key, mixed>  $produtos
     * @return array{valid: bool, faltando: list<string>}
     */
    public function validarCurvasObrigatorias(array $produtos): array
    {
        $faltando = [];

        foreach ($produtos as $produto) {
            if (! is_array($produto)) {
                continue;
            }

            $nome = (string) ($produto['nome'] ?? 'Produto');
            $curvaVendas = $this->extrairCurva($produto['curva_vendas'] ?? null);

            if ($curvaVendas === []) {
                $faltando[] = "{$nome}: curva_vendas";
            }
        }

        return [
            'valid' => $faltando === [],
            'faltando' => $faltando,
        ];
    }
}
