<?php

namespace App\Services\Tenant\Viabilidade;

/**
 * CurvaService — Utilitários de curva para viabilidade.
 *
 * Todas as curvas vêm da tabela `produtos`. O tamanho do array
 * determina a quantidade de meses. Não há dados hardcoded.
 */
class CurvaService
{
    /** Normaliza curva para soma = 100%. */
    public function normalizarCurva(array $curva): array
    {
        $soma = array_sum($curva);
        if ($soma > 0 && abs($soma - 100) > 0.1) {
            $fator = 100 / $soma;

            return array_map(fn ($v) => $v * $fator, $curva);
        }

        return $curva;
    }

    /**
     * Extrai curva de array ou JSON do produto.
     *
     * @return array<float> Valores numéricos
     */
    public function extrairCurva(array|string|null $valor): array
    {
        if (is_string($valor)) {
            $decoded = json_decode($valor, true);
            $valor = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($valor) || empty($valor)) {
            return [];
        }

        return array_values(array_filter(array_map('floatval', $valor), fn ($v) => $v >= 0));
    }

    /**
     * Valida se todos os produtos possuem curvas obrigatórias.
     *
     * @return array{valid: bool, faltando: array<string>}
     */
    public function validarCurvasObrigatorias(array $produtos): array
    {
        $faltando = [];
        foreach ($produtos as $i => $produto) {
            if (empty($this->extrairCurva($produto['curva_vendas'] ?? null))) {
                $faltando[] = "produto[{$i}].curva_vendas";
            }
            if (empty($this->extrairCurva($produto['curva_obra'] ?? null))) {
                $faltando[] = "produto[{$i}].curva_obra";
            }
        }

        return ['valid' => empty($faltando), 'faltando' => $faltando];
    }

    /** Ajusta curva para ter exatamente $meses elementos (corta ou preenche com zeros). */
    public function ajustarCurva(array $curva, int $meses): array
    {
        $atual = count($curva);
        if ($atual >= $meses) {
            return array_slice($curva, 0, $meses);
        }

        return array_pad($curva, $meses, 0.0);
    }

    /** Interpolação linear para redimensionar curva. */
    public function interpolarCurva(array $curva, int $meses): array
    {
        $original = count($curva);
        if ($original <= 1) {
            return array_fill(0, $meses, $curva[0] ?? 0);
        }

        $nova = [];
        for ($i = 0; $i < $meses; $i++) {
            $pos = ($i / max(1, $meses - 1)) * ($original - 1);
            $idx = (int) floor($pos);
            $frac = $pos - $idx;
            $valor = ($idx + 1 < $original)
                ? $curva[$idx] + ($curva[$idx + 1] - $curva[$idx]) * $frac
                : $curva[$idx];
            $nova[] = max(0, $valor);
        }

        return $this->normalizarCurva($nova);
    }
}
