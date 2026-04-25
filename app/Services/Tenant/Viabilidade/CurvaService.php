<?php

namespace App\Services\Tenant\Viabilidade;

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
    // Constantes para Tipos de Produto
    public const TIPO_2_DORM = '2_dorm';
    public const TIPO_3_DORM = '3_dorm';
    public const TIPO_LOTES = 'lotes';

    /**
     * Curvas de desembolso de obra por período de construção (Curva S)
     * Valores em percentual do custo total de obra.
     * Fonte: Dados históricos do projeto legado.
     */
    private array $curvasObra = [
        18 => [1.5, 2.0, 3.0, 4.5, 5.5, 6.5, 7.5, 8.5, 9.0, 9.0, 8.5, 7.5, 6.5, 5.5, 4.5, 4.0, 3.5, 2.5],
        20 => [0.8, 1.3, 1.5, 3.5, 4.5, 4.8, 6.0, 6.5, 7.0, 8.0, 8.0, 9.0, 8.0, 7.0, 6.5, 4.5, 3.0, 1.5, 1.2],
        24 => [0.8, 1.3, 1.5, 3.5, 4.0, 4.0, 4.5, 4.5, 5.5, 6.0, 7.0, 7.0, 7.5, 6.5, 6.0, 5.0, 5.0, 5.0, 4.5, 4.5, 2.5, 2.0, 1.5, 0.5],
        30 => [2.0, 2.5, 2.9, 3.3, 3.5, 4.5, 5.0, 6.0, 7.0, 7.0, 5.5, 5.5, 4.0, 4.0, 4.0, 4.0, 4.0, 3.0, 3.0, 3.0, 2.8, 2.5, 2.5, 2.5, 2.0, 1.0, 1.5, 0.5, 0.5, 0.5],
        36 => [1.0, 1.2, 1.4, 1.6, 1.8, 2.0, 2.2, 2.4, 2.6, 2.8, 3.0, 3.2, 3.4, 3.6, 3.8, 4.0, 4.2, 4.3, 4.3, 4.3, 4.3, 4.0, 3.8, 3.6, 3.4, 3.2, 3.0, 2.8, 2.6, 2.4, 2.2, 2.0, 1.8, 1.6, 1.4, 0.8]
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
    public function getCurvaObraParaPrazo(int $mesesTotal): array
    {
        $prazoMaisProximo = $this->getPrazoMaisProximo($mesesTotal);
        $curva = $this->curvasObra[$prazoMaisProximo];

        return $this->normalizarCurva($curva);
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
     * Retorna curva de vendas por tipo de produto
     * 
     * @param int $mesesVenda Duração em meses para as vendas
     * @param string $tipoProduto Tipo do produto (2_dorm, 3_dorm, lotes)
     * @return array Curva de vendas normalizada
     */
    public function getCurvaVendas(int $mesesVenda, string $tipoProduto = self::TIPO_2_DORM): array
    {
        $curvasBase = config('viabilidade.curvas_vendas');
        $curva = $curvasBase[$tipoProduto] ?? $curvasBase[self::TIPO_2_DORM];

        // Ajustar tamanho da curva
        if (count($curva) < $mesesVenda) {
            $curva = array_pad($curva, $mesesVenda, 0);
        } elseif (count($curva) > $mesesVenda) {
            $curva = array_slice($curva, 0, $mesesVenda);
        }

        return $this->normalizarCurva($curva);
    }

    /**
     * Retorna os meses de curva padrão para um tipo de produto
     */
    public function getMesesCurvaPadrao(string $tipoProduto): int
    {
        return $tipoProduto === self::TIPO_LOTES ? 18 : 15;
    }

    /**
     * Determina o tipo de produto predominante baseado nos produtos
     * 
     * @param array $areaProdutos Coleção de areaProdutos
     * @return string Tipo de produto predominante
     */
    public function determinarTipoProduto($areaProdutos): string
    {
        $contagem = [
            self::TIPO_2_DORM => 0,
            self::TIPO_3_DORM => 0,
            self::TIPO_LOTES => 0
        ];

        foreach ($areaProdutos as $areaProduto) {
            if (!$areaProduto || !$areaProduto->produto)
                continue;

            $nomeProduto = strtolower($areaProduto->produto->name ?? '');

            if (str_contains($nomeProduto, '3_dorm') || str_contains($nomeProduto, '3 dorm')) {
                $contagem[self::TIPO_3_DORM]++;
            } elseif (str_contains($nomeProduto, 'lote') || str_contains($nomeProduto, 'terreno')) {
                $contagem[self::TIPO_LOTES]++;
            } else {
                $contagem[self::TIPO_2_DORM]++;
            }
        }

        return array_search(max($contagem), $contagem);
    }

    /**
     * Normaliza uma curva para que a soma seja exatamente 100%
     */
    public function normalizarCurva(array $curva): array
    {
        $soma = array_sum($curva);

        if ($soma > 0 && abs($soma - 100) > 0.1) {
            $fator = 100 / $soma;
            $curva = array_map(fn($val) => $val * $fator, $curva);
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

        return array_values($curva);
    }

    public function ajustarCurva(array $curva, int $meses): array
    {
        $meses = max(0, $meses);
        $curva = array_values($curva);

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

    public function interpolarCurva(array $curva, int $meses): array
    {
        $meses = max(0, $meses);
        $curva = array_values($curva);
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

    public function validarCurvasObrigatorias(array $produtos): array
    {
        $faltando = [];

        foreach ($produtos as $produto) {
            if (! is_array($produto)) {
                continue;
            }

            $nome = (string) ($produto['nome'] ?? 'Produto');
            $curvaVendas = $this->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaObra = $this->extrairCurva($produto['curva_obra'] ?? null);

            if ($curvaVendas === []) {
                $faltando[] = "{$nome}: curva_vendas";
            }

            if ($curvaObra === []) {
                $faltando[] = "{$nome}: curva_obra";
            }
        }

        return [
            'valid' => $faltando === [],
            'faltando' => $faltando,
        ];
    }
}
