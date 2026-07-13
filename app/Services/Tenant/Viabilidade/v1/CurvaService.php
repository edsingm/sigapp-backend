<?php

declare(strict_types=1);

namespace App\Services\Tenant\Viabilidade\v1;

use Carbon\Carbon;
use RuntimeException;

/**
 * CurvaService — curvas oficiais de obra (Aux_Obras) e curva financeira de medição CEF.
 *
 * Fonte canônica: docs/viabilidade-modelo → aba Aux_Obras (prazos 12–36).
 * Prazos fora da tabela usam redistribuição monotônica com warning explícito.
 */
class CurvaService
{
    /**
     * @var array<int, list<float>>|null
     */
    private static ?array $curvasOficiais = null;

    /**
     * @var list<string>
     */
    private array $warnings = [];

    /**
     * @return list<string>
     */
    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    /**
     * Percentual de desembolso de obra no mês (1..N), curva física normalizada a 100%.
     */
    public function getPercentualCustoObra(int $mesesTotal, int $mesAtual): float
    {
        $curva = $this->getCurvaObraParaPrazo($mesesTotal);
        $indice = $mesAtual - 1;

        return $curva[$indice] ?? 0.0;
    }

    /**
     * Curva física de obra normalizada (soma = 100%).
     *
     * @return list<float>
     */
    public function getCurvaObraParaPrazo(int $mesesTotal): array
    {
        return $this->normalizarCurva($this->resolverCurvaBase($mesesTotal));
    }

    /**
     * Curva base (percentuais oficiais ou interpolados) sem reescalar além da normalização.
     *
     * @return list<float>
     */
    public function getCurvaObraBaseParaPrazo(int $mesesTotal): array
    {
        return $this->resolverCurvaBase($mesesTotal);
    }

    /**
     * Curva financeira de medição CEF.
     *
     * Regra oficial dos 5% finais (Lista de Desenv. / Aux_Obras):
     * - 95% durante a obra (curva física reescalada);
     * - 2% dois meses após a entrega (índice mesesObra+2);
     * - 3% seis meses após a entrega (índice mesesObra+6).
     *
     * Índices 0-based relativos ao início da obra. O vetor se estende até mesesObra+6.
     *
     * @return array<int, float>
     */
    public function getCurvaFinanceiraMedicaoParaPrazo(int $mesesTotal, float $obraAteLancamento = 0.0): array
    {
        $mesesTotal = max(1, $mesesTotal);
        $obraAteLancamento = max(0.0, min(1.0, $obraAteLancamento));

        $curvaFisica = $this->getCurvaObraParaPrazo($mesesTotal);
        // 95% do total é liberado ao longo da obra; 5% retidos.
        $fatorObra = 0.95;
        $curvaFinanceira = array_fill(0, $mesesTotal + 7, 0.0);

        $parcelaObra = [];
        foreach ($curvaFisica as $indice => $percentual) {
            $parcelaObra[$indice] = $percentual * $fatorObra;
        }

        // obra_ate_lancamento: antecipa essa fração do desembolso de medição para o 1º mês de obra,
        // reduzindo proporcionalmente os meses seguintes (efeito real na curva financeira).
        if ($obraAteLancamento > 0.0 && $parcelaObra !== []) {
            $totalObra = array_sum($parcelaObra);
            $antecipado = $totalObra * $obraAteLancamento;
            $restanteAlvo = $totalObra - $antecipado;
            $restanteAtual = $totalObra - ($parcelaObra[0] ?? 0.0);
            $parcelaObra[0] = ($parcelaObra[0] ?? 0.0) + $antecipado;
            if ($restanteAtual > 0.0) {
                $fatorRestante = $restanteAlvo / $restanteAtual;
                foreach ($parcelaObra as $indice => $valor) {
                    if ($indice === 0) {
                        continue;
                    }
                    $parcelaObra[$indice] = $valor * $fatorRestante;
                }
            }
        }

        foreach ($parcelaObra as $indice => $percentual) {
            $curvaFinanceira[$indice] = round($percentual, 6);
        }

        // 5% finais: 2% em entrega+2 e 3% em entrega+6 (índices 1-based = mesesObra+N).
        $curvaFinanceira[$mesesTotal + 1] = 2.0; // +2 meses após fim da obra (0-based: mesesTotal+1)
        $curvaFinanceira[$mesesTotal + 5] = 3.0; // +6 meses (0-based: mesesTotal+5)

        $soma = array_sum($curvaFinanceira);
        if (abs($soma - 100.0) > 0.01 && $soma > 0) {
            $ajuste = 100.0 - $soma;
            $curvaFinanceira[$mesesTotal - 1] = ($curvaFinanceira[$mesesTotal - 1] ?? 0.0) + $ajuste;
        }

        return $curvaFinanceira;
    }

    /**
     * @return list<float>
     */
    private function resolverCurvaBase(int $mesesTotal): array
    {
        $mesesTotal = max(1, $mesesTotal);
        $curvas = $this->curvasOficiais();

        if (isset($curvas[$mesesTotal])) {
            return $curvas[$mesesTotal];
        }

        $prazos = array_keys($curvas);
        sort($prazos);

        $min = $prazos[0];
        $max = $prazos[array_key_last($prazos)];

        if ($mesesTotal < $min) {
            $this->warnings[] = "Prazo de obra {$mesesTotal}m abaixo da tabela oficial ({$min}–{$max}); redistribuindo curva de {$min}m.";

            return $this->interpolarCurva($curvas[$min], $mesesTotal);
        }

        if ($mesesTotal > $max) {
            $this->warnings[] = "Prazo de obra {$mesesTotal}m acima da tabela oficial ({$min}–{$max}); redistribuindo monotônica a partir de {$max}m (não reutiliza o vetor de {$max} posições).";

            return $this->interpolarCurva($curvas[$max], $mesesTotal);
        }

        // Entre dois prazos tabelados: interpola a curva-alvo a partir do vizinho mais próximo
        // e redistribui monotônica para o tamanho exato (nunca devolve o vetor vizinho cru).
        $inferior = $min;
        $superior = $max;
        foreach ($prazos as $prazo) {
            if ($prazo <= $mesesTotal) {
                $inferior = $prazo;
            }
            if ($prazo >= $mesesTotal) {
                $superior = $prazo;
                break;
            }
        }

        $this->warnings[] = "Prazo de obra {$mesesTotal}m sem curva oficial; interpolando entre {$inferior}m e {$superior}m.";

        if ($inferior === $superior) {
            return $this->interpolarCurva($curvas[$inferior], $mesesTotal);
        }

        $peso = ($mesesTotal - $inferior) / max(1, $superior - $inferior);
        $curvaInf = $this->interpolarCurva($curvas[$inferior], $mesesTotal);
        $curvaSup = $this->interpolarCurva($curvas[$superior], $mesesTotal);
        $misturada = [];
        for ($i = 0; $i < $mesesTotal; $i++) {
            $misturada[] = (($curvaInf[$i] ?? 0.0) * (1 - $peso)) + (($curvaSup[$i] ?? 0.0) * $peso);
        }

        return $this->normalizarCurva($misturada);
    }

    /**
     * @return array<int, list<float>>
     */
    private function curvasOficiais(): array
    {
        if (self::$curvasOficiais !== null) {
            return self::$curvasOficiais;
        }

        $path = __DIR__.'/Data/curvas_obra_aux_obras.json';
        if (! is_file($path)) {
            throw new RuntimeException('Fixture de curvas oficiais Aux_Obras não encontrada: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! is_array($decoded['curves'] ?? null)) {
            throw new RuntimeException('Fixture de curvas oficiais inválida.');
        }

        $map = [];
        foreach ($decoded['curves'] as $prazo => $valores) {
            if (! is_array($valores)) {
                continue;
            }
            $map[(int) $prazo] = array_map(static fn (mixed $v): float => (float) $v, array_values($valores));
        }

        ksort($map);
        self::$curvasOficiais = $map;

        return self::$curvasOficiais;
    }

    /**
     * @param  list<float>  $curva
     * @return list<float>
     */
    public function normalizarCurva(array $curva): array
    {
        $soma = array_sum($curva);
        if ($soma <= 0.0) {
            return $curva;
        }

        if (abs($soma - 100.0) <= 0.0001) {
            return array_values($curva);
        }

        $fator = 100.0 / $soma;

        return array_map(static fn (float $val): float => $val * $fator, array_values($curva));
    }

    /**
     * @param  list<float>  $curva
     * @return array<string, float>
     */
    public function distribuirPorCurva(float $total, Carbon $dataInicio, array $curva): array
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
     * Redistribuição monotônica: redimensiona preservando a forma e normaliza a 100%.
     *
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

        // Trabalha no acumulado para garantir monotonicidade do acumulado final.
        $acumulado = [];
        $running = 0.0;
        foreach ($curva as $valor) {
            $running += max(0.0, (float) $valor);
            $acumulado[] = $running;
        }
        $total = $running > 0 ? $running : 100.0;

        $resultadoAcum = [];
        for ($i = 0; $i < $meses; $i++) {
            $pos = ($i * ($n - 1)) / ($meses - 1);
            $left = (int) floor($pos);
            $right = min($n - 1, (int) ceil($pos));
            $weight = $pos - $left;
            $vl = (float) ($acumulado[$left] ?? 0.0);
            $vr = (float) ($acumulado[$right] ?? $vl);
            $resultadoAcum[] = ($vl * (1 - $weight)) + ($vr * $weight);
        }

        // Garante monotonia e fecha em 100% do total original antes de normalizar.
        $prev = 0.0;
        $mensal = [];
        for ($i = 0; $i < $meses; $i++) {
            $atual = max($prev, min($total, $resultadoAcum[$i]));
            if ($i === $meses - 1) {
                $atual = $total;
            }
            $mensal[] = $atual - $prev;
            $prev = $atual;
        }

        return $this->normalizarCurva($mensal);
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

    /**
     * @return list<int>
     */
    public function prazosOficiais(): array
    {
        return array_keys($this->curvasOficiais());
    }
}
