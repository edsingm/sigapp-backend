<?php

declare(strict_types=1);

namespace App\Services\Tenant\Viabilidade\v1;

use App\Models\Tenant\Viabilidade;
use Carbon\CarbonInterface;

/**
 * Normaliza o snapshot canônico da viabilidade (schema v1 legado e v2).
 */
class ViabilidadeSnapshotService
{
    public const SCHEMA_VERSION = 2;

    public const ENGINE_VERSION = '2.0.0';

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array<string, mixed>>  $produtos
     * @param  array<string, mixed>  $premissas
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function buildCanonical(
        array $inputs,
        array $produtos,
        array $premissas = [],
        array $existing = [],
        ?string $calculatedAt = null,
    ): array {
        $normalizedProdutos = $this->normalizeProdutos($produtos);
        $normalizedInputs = $this->normalizeInputs($inputs, $normalizedProdutos);

        $snapshot = [
            'schema_version' => self::SCHEMA_VERSION,
            'calculation_engine_version' => self::ENGINE_VERSION,
            'calculated_at' => $calculatedAt,
            'inputs' => $normalizedInputs,
            'premissas' => $premissas,
            'form_values' => $normalizedInputs['form_values'] ?? [],
            'produtos' => $normalizedProdutos,
            'historico' => is_array($existing['historico'] ?? null) ? $existing['historico'] : [],
            'derived' => is_array($existing['derived'] ?? null) ? $existing['derived'] : [],
            'warnings' => is_array($existing['warnings'] ?? null) ? $existing['warnings'] : [],
        ];

        $snapshot['input_hash'] = $this->hashPayload([
            'inputs' => $normalizedInputs,
            'produtos' => $normalizedProdutos,
            'premissas' => $premissas,
        ]);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function readCanonical(array $snapshot): array
    {
        $schemaVersion = (int) ($snapshot['schema_version'] ?? 1);

        if ($schemaVersion >= 2) {
            return $snapshot;
        }

        // Legado v1: form_values no topo; produtos podem estar em form_values.produtos.
        $formValues = is_array($snapshot['form_values'] ?? null) ? $snapshot['form_values'] : [];
        $produtos = is_array($formValues['produtos'] ?? null)
            ? $formValues['produtos']
            : (is_array($snapshot['produtos'] ?? null) ? $snapshot['produtos'] : []);

        return $this->buildCanonical(
            inputs: [
                'terreno_id' => $formValues['terreno_id'] ?? null,
                'data_lancamento' => $formValues['data_lancamento'] ?? null,
                'perfil_financiamento' => $formValues['perfil_financiamento'] ?? null,
                'form_values' => $formValues,
            ],
            produtos: is_array($produtos) ? $produtos : [],
            premissas: is_array($snapshot['premissas'] ?? null) ? $snapshot['premissas'] : [],
            existing: $snapshot,
            calculatedAt: is_string($snapshot['calculado_em'] ?? null) ? $snapshot['calculado_em'] : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return list<array<string, mixed>>
     */
    public function extractProdutos(?array $snapshot): array
    {
        if (! is_array($snapshot)) {
            return [];
        }

        $canonical = $this->readCanonical($snapshot);
        $produtos = $canonical['produtos'] ?? [];

        return is_array($produtos) ? array_values(array_filter($produtos, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $resultados
     */
    public function resultHash(array $resultados): string
    {
        return $this->hashPayload([
            'vgv' => $resultados['vgv'] ?? null,
            'totalUnidades' => $resultados['totalUnidades'] ?? null,
            'dre_itens' => $resultados['dre_itens'] ?? null,
            'indicadores' => $resultados['indicadores'] ?? null,
            'totais' => $resultados['totais'] ?? null,
        ]);
    }

    public function attachResultMetadata(array $snapshot, array $resultados, ?string $calculatedAt = null): array
    {
        $snapshot['calculated_at'] = $calculatedAt ?? now()->toIso8601String();
        $snapshot['result_hash'] = $this->resultHash($resultados);
        $snapshot['calculation_engine_version'] = self::ENGINE_VERSION;
        $snapshot['schema_version'] = (int) ($snapshot['schema_version'] ?? self::SCHEMA_VERSION);
        $snapshot['parametros'] = $resultados['parametros_utilizados'] ?? ($snapshot['parametros'] ?? []);
        $snapshot['indicadores'] = $resultados['indicadores'] ?? ($snapshot['indicadores'] ?? []);
        $snapshot['vgv'] = $resultados['vgv'] ?? ($snapshot['vgv'] ?? null);
        $snapshot['total_unidades'] = $resultados['totalUnidades'] ?? ($snapshot['total_unidades'] ?? null);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array<string, mixed>>  $produtos
     * @return array<string, mixed>
     */
    private function normalizeInputs(array $inputs, array $produtos): array
    {
        $formValues = is_array($inputs['form_values'] ?? null)
            ? $inputs['form_values']
            : $inputs;

        unset($formValues['produtos']);

        ksort($formValues);

        $dataLancamento = $inputs['data_lancamento']
            ?? $formValues['data_lancamento']
            ?? null;

        if ($dataLancamento instanceof CarbonInterface) {
            $dataLancamento = $dataLancamento->toDateString();
        } elseif (is_string($dataLancamento) && $dataLancamento !== '') {
            $dataLancamento = substr($dataLancamento, 0, 10);
        }

        return [
            'terreno_id' => isset($inputs['terreno_id'])
                ? (int) $inputs['terreno_id']
                : (isset($formValues['terreno_id']) ? (int) $formValues['terreno_id'] : null),
            'data_lancamento' => $dataLancamento,
            'perfil_financiamento' => $inputs['perfil_financiamento']
                ?? $formValues['perfil_financiamento']
                ?? null,
            'form_values' => $formValues,
            'produtos' => $produtos,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $produtos
     * @return list<array<string, mixed>>
     */
    private function normalizeProdutos(array $produtos): array
    {
        $normalized = [];

        foreach ($produtos as $produto) {
            if (! is_array($produto)) {
                continue;
            }

            $id = (int) ($produto['id'] ?? $produto['terreno_produto_id'] ?? 0);
            $row = [
                'id' => $id,
                'unidades' => (float) ($produto['unidades'] ?? $produto['quantidade_unidades'] ?? 0),
                'valor' => (float) ($produto['valor'] ?? $produto['preco'] ?? 0),
                'permuta' => (float) ($produto['permuta'] ?? $produto['permutas'] ?? 0),
                'pgto_por_lote' => (float) ($produto['pgto_por_lote'] ?? 0),
                'custo_m2' => (float) ($produto['custo_m2'] ?? 0),
                'custo_infra' => (float) ($produto['custo_infra'] ?? $produto['custo_infraestrutura'] ?? 0),
            ];

            if (isset($produto['_nome']) || isset($produto['nome'])) {
                $row['_nome'] = $produto['_nome'] ?? $produto['nome'];
            }

            if (isset($produto['_area_privativa']) || isset($produto['metragem'])) {
                $row['_area_privativa'] = (float) ($produto['_area_privativa'] ?? $produto['metragem']);
            }

            $normalized[] = $row;
        }

        usort($normalized, static fn (array $a, array $b): int => ($a['id'] <=> $b['id']));

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        $json = json_encode($this->ksortRecursive($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'sha256:'.hash('sha256', is_string($json) ? $json : '');
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function ksortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->ksortRecursive($item);
            }
        }

        ksort($value);

        return $value;
    }

    public function fromViabilidade(Viabilidade $viabilidade): array
    {
        $snapshot = $viabilidade->getAttribute('premissas_snapshot');

        return is_array($snapshot) ? $this->readCanonical($snapshot) : [];
    }
}
