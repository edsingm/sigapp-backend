<?php

declare(strict_types=1);

namespace App\Exports\Tenant;

/**
 * Compat: export de uma única seção agregada.
 * Preferir ReportRunWorkbookExport para multi-dataset / detail.
 *
 * @phpstan-type ReportRow array{
 *     dataset: string,
 *     dimension: string,
 *     label: string,
 *     count: int,
 *     sum_valor: float|null,
 *     as_of: string
 * }
 */
class ReportRunExport extends ReportRunSheetExport
{
    /**
     * @param  list<ReportRow>  $rows
     * @param  list<string>  $metrics
     */
    public function __construct(array $rows, array $metrics = ['count'])
    {
        parent::__construct([
            'dataset' => (string) ($rows[0]['dataset'] ?? 'relatorio'),
            'dataset_label' => 'Relatório',
            'mode' => 'aggregate',
            'metrics' => $metrics,
            'rows' => $rows,
        ]);
    }
}
