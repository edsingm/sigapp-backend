<?php

declare(strict_types=1);

namespace App\Services\Tenant\Viabilidade;

use App\Exceptions\PlanFeatureDisabledException;
use App\Models\Central\Tenant;
use App\Services\PlanMatrixService;

class ViabilidadeResultProjector
{
    /** @var array<string, list<string>> */
    private const RESULT_KEYS = [
        'summary' => [
            'vgv',
            'custoTotal',
            'calculation_engine_version',
            'warnings',
            'produtos',
        ],
        'kpis' => ['indicadores'],
        'dre' => [
            'dre_itens',
            'dre_caixa',
            'dre_contabil_poc',
            'dre_contabil_poc_mensal',
            'dre_contabil_poc_mensal_blocos',
            'ponte_reconciliacao',
            'reconciliation',
        ],
        'cash_flow' => ['fluxo_mensal', 'fluxo_mensal_financeiro', 'totais'],
        'comercial' => ['dados_produtos'],
        'premises' => ['parametros_utilizados'],
        'charts' => ['charts', 'chart_series', 'series'],
    ];

    /** @var array<string, string> */
    private const INCLUDE_SECTIONS = [
        'resumo' => 'summary',
        'produtos_resumo' => 'summary',
        'indicadores' => 'kpis',
        'dre' => 'dre',
        'dre_caixa' => 'dre',
        'dre_contabil_poc' => 'dre',
        'dre_contabil_poc_mensal' => 'dre',
        'dre_contabil_poc_mensal_blocos' => 'dre',
        'ponte_reconciliacao' => 'dre',
        'fluxo_mensal' => 'cash_flow',
        'fluxo_mensal_financeiro' => 'cash_flow',
        'totais' => 'cash_flow',
        'dados_produtos' => 'comercial',
        'parametros_utilizados' => 'premises',
        'charts' => 'charts',
        'resultados_dre' => 'dre',
        'monthly_cash_flow' => 'cash_flow',
    ];

    public function __construct(
        private readonly PlanMatrixService $planMatrix,
    ) {}

    /** @return array<string, mixed> */
    public function project(array $result): array
    {
        $projected = [];

        foreach (self::RESULT_KEYS as $section => $keys) {
            if (! $this->allows($section)) {
                continue;
            }

            foreach ($keys as $key) {
                if (array_key_exists($key, $result)) {
                    $projected[$key] = $result[$key];
                }
            }
        }

        return $projected;
    }

    public function assertExplicitIncludesAllowed(?string $raw): void
    {
        if ($raw === null || trim($raw) === '') {
            return;
        }

        $includes = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if (in_array('*', $includes, true)) {
            foreach (array_keys(self::RESULT_KEYS) as $section) {
                $this->assertAllowed($section);
            }

            return;
        }

        foreach ($includes as $include) {
            $section = self::INCLUDE_SECTIONS[$include] ?? null;
            if ($section !== null) {
                $this->assertAllowed($section);
            }
        }
    }

    public function allows(string $section): bool
    {
        $tenant = tenancy()->tenant;
        if (! tenancy()->initialized || ! $tenant instanceof Tenant) {
            return true;
        }

        return $this->planMatrix->hasFeatureForTenant(
            $tenant,
            "viabilities.{$section}",
        );
    }

    private function assertAllowed(string $section): void
    {
        if (! $this->allows($section)) {
            throw new PlanFeatureDisabledException("viabilities.{$section}");
        }
    }
}
