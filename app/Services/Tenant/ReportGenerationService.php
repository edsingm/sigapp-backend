<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Exports\Tenant\ReportRunWorkbookExport;
use App\Models\Tenant\ReportRun;
use App\Models\Tenant\ReportSchedule;
use App\Models\Tenant\User;
use App\Notifications\ReportScheduleReadyNotification;
use App\Repositories\Tenant\ReportRunRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

class ReportGenerationService
{
    /** @var list<string> */
    private const LEGAL_RICH_METRICS = [
        'sum_custo_planejado',
        'sum_custo_realizado',
        'avg_critical_days',
        'sum_critical_days',
    ];

    /** @var list<string> */
    private const LEGAL_VIRTUAL_COLUMNS = [
        'custo_planejado',
        'custo_realizado',
        'critical_path_days',
    ];

    public function __construct(
        private readonly StorageQuotaService $storageQuota,
        private readonly ReportRunRepository $repository,
        private readonly ReportCatalogService $catalog,
        private readonly TerrenoExportService $terrenoExport,
        private readonly ReportLegalizacaoMetricsService $legalMetrics,
    ) {}

    public function generate(ReportRun $run): void
    {
        $definition = $run->definition_snapshot ?? [];
        $format = in_array($run->format, $this->catalog->formatKeys(), true) ? $run->format : 'csv';
        $sections = $this->buildSections($definition, $run->filters ?? []);
        $extension = $this->catalog->extensionFor($format);
        $path = 'reports/runs/'.$run->id.'.'.$extension;

        match ($format) {
            'xlsx' => $this->storeExcel($path, $sections),
            'pdf' => $this->storePdf($run, $path, $sections, $definition),
            default => $this->storeCsv($path, $sections),
        };

        $this->storageQuota->commitFile(
            's3',
            $path,
            fn (int $size): ReportRun => $this->repository->update($run, [
                'status' => 'completed',
                'progress' => 100,
                'storage_disk' => 's3',
                'storage_path' => $path,
                'mime_type' => $this->catalog->mimeTypeFor($format),
                'size' => $size,
                'completed_by' => $run->requested_by,
                'completed_at' => now(),
            ]),
        );

        $this->notifyScheduleIfNeeded($run->fresh(['requester', 'schedule']) ?? $run);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function buildSections(array $definition, array $filters): array
    {
        $mode = (string) ($definition['mode'] ?? ReportCatalogService::MODE_AGGREGATE);
        if (! in_array($mode, $this->catalog->modeKeys(), true)) {
            $mode = ReportCatalogService::MODE_AGGREGATE;
        }

        /** @var list<string> $datasets */
        $datasets = array_values(array_filter(
            $definition['datasets'] ?? ['terrenos'],
            static fn (mixed $dataset): bool => is_string($dataset),
        ));
        if ($datasets === []) {
            $datasets = ['terrenos'];
        }

        /** @var list<string> $requestedDimensions */
        $requestedDimensions = array_values(array_filter(
            $definition['dimensions'] ?? [],
            static fn (mixed $value): bool => is_string($value),
        ));
        /** @var list<string> $requestedMetrics */
        $requestedMetrics = array_values(array_filter(
            $definition['metrics'] ?? ['count'],
            static fn (mixed $value): bool => is_string($value),
        ));
        /** @var list<string> $requestedColumns */
        $requestedColumns = array_values(array_filter(
            $definition['columns'] ?? [],
            static fn (mixed $value): bool => is_string($value),
        ));
        /** @var list<string> $charts */
        $charts = array_values(array_filter(
            $definition['charts'] ?? ['table'],
            static fn (mixed $value): bool => is_string($value),
        ));

        $sections = [];
        foreach ($datasets as $dataset) {
            if (! in_array($dataset, $this->catalog->datasetKeys(), true)) {
                continue;
            }

            if ($mode === ReportCatalogService::MODE_DETAIL) {
                $columns = $this->resolveColumns($dataset, $requestedColumns);
                $rows = $this->buildDetailRows($dataset, $columns, $filters);
                $sections[] = [
                    'dataset' => $dataset,
                    'dataset_label' => $this->catalog->labelForDataset($dataset),
                    'mode' => ReportCatalogService::MODE_DETAIL,
                    'columns' => $columns,
                    'column_labels' => array_map(
                        fn (string $column): string => $this->catalog->labelForColumn($dataset, $column),
                        $columns,
                    ),
                    'charts' => $charts,
                    'rows' => $rows,
                    'chart_bars' => [],
                ];

                continue;
            }

            $dimension = $this->resolveDimension($dataset, $requestedDimensions);
            $metrics = $this->resolveMetrics($dataset, $requestedMetrics);
            $rows = $this->buildAggregateRows($dataset, $dimension, $metrics, $filters);
            $sections[] = [
                'dataset' => $dataset,
                'dataset_label' => $this->catalog->labelForDataset($dataset),
                'mode' => ReportCatalogService::MODE_AGGREGATE,
                'dimension' => $dimension,
                'metrics' => $metrics,
                'charts' => $charts,
                'rows' => $rows,
                'chart_bars' => $this->buildChartBars($rows, $metrics),
            ];
        }

        return $sections;
    }

    /**
     * @param  list<string>  $metrics
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function buildRows(string $dataset, string $dimension, array $metrics, array $filters): array
    {
        return $this->buildAggregateRows($dataset, $dimension, $metrics, $filters);
    }

    /**
     * @param  list<string>  $metrics
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function buildAggregateRows(string $dataset, string $dimension, array $metrics, array $filters): array
    {
        if ($dataset === 'legalizacoes' && array_intersect($metrics, self::LEGAL_RICH_METRICS) !== []) {
            return $this->buildLegalizacaoAggregateRows($dimension, $metrics, $filters);
        }

        $column = $this->catalog->dimensionColumn($dataset, $dimension);
        $valueColumn = $this->catalog->valueColumn($dataset);
        $includeSum = in_array('sum_valor', $metrics, true) && $valueColumn !== null;

        $query = $this->queryFor($dataset);
        $this->applyFilters($query, $dataset, $filters);

        $select = [$column, DB::raw('count(*) as total')];
        if ($includeSum) {
            $select[] = DB::raw('sum('.$valueColumn.') as sum_valor');
        }

        $groups = $query->select($select)
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(ReportCatalogService::AGGREGATE_LIMIT)
            ->get();

        $asOf = now()->toIso8601String();
        $rows = [];
        foreach ($groups as $group) {
            $label = $group->{$column};
            $rows[] = [
                'dataset' => $dataset,
                'dimension' => $dimension,
                'label' => $label === null || $label === '' ? 'Não informado' : (string) $label,
                'count' => (int) $group->total,
                'sum_valor' => $includeSum ? (float) ($group->sum_valor ?? 0) : null,
                'sum_custo_planejado' => null,
                'sum_custo_realizado' => null,
                'avg_critical_days' => null,
                'sum_critical_days' => null,
                'as_of' => $asOf,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function buildDetailRows(string $dataset, array $columns, array $filters): array
    {
        $map = $this->catalog->columnMapFor($dataset);
        $physical = [];
        /** @var list<string> $virtual */
        $virtual = [];
        foreach ($columns as $column) {
            if (in_array($column, self::LEGAL_VIRTUAL_COLUMNS, true) && $dataset === 'legalizacoes') {
                $virtual[] = $column;

                continue;
            }
            if (isset($map[$column])) {
                $physical[$column] = $map[$column];
            }
        }

        if ($physical === [] && $virtual === []) {
            return [];
        }

        $query = $this->queryFor($dataset);
        $this->applyFilters($query, $dataset, $filters);

        $select = array_values(array_unique(array_merge(array_values($physical), ['id'])));
        $records = $query
            ->select($select)
            ->orderByDesc('id')
            ->limit(ReportCatalogService::DETAIL_LIMIT)
            ->get();

        $legalMetrics = [];
        if ($dataset === 'legalizacoes' && $virtual !== []) {
            /** @var list<int> $ids */
            $ids = array_values($records->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
            $legalMetrics = $this->legalMetrics->metricsByLegalizacao($ids);
        }

        $asOf = now()->toIso8601String();
        $rows = [];
        foreach ($records as $record) {
            $row = ['as_of' => $asOf];
            foreach ($physical as $logical => $dbColumn) {
                $value = $record->{$dbColumn} ?? null;
                $row[$logical] = is_scalar($value) || $value === null ? $value : (string) $value;
            }
            if ($dataset === 'legalizacoes' && $virtual !== []) {
                $metrics = $legalMetrics[(int) $record->id] ?? null;
                foreach ($virtual as $virtualColumn) {
                    if ($virtualColumn === 'custo_planejado') {
                        $row[$virtualColumn] = $metrics['custo_planejado'] ?? 0;
                    } elseif ($virtualColumn === 'custo_realizado') {
                        $row[$virtualColumn] = $metrics['custo_realizado'] ?? 0;
                    } else {
                        $row[$virtualColumn] = $metrics['critical_path_days'] ?? null;
                    }
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $metrics
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function buildLegalizacaoAggregateRows(string $dimension, array $metrics, array $filters): array
    {
        $column = $this->catalog->dimensionColumn('legalizacoes', $dimension);
        $query = $this->queryFor('legalizacoes');
        $this->applyFilters($query, 'legalizacoes', $filters);
        $legalizacoes = $query
            ->select(['id', $column])
            ->orderByDesc('id')
            ->limit(ReportCatalogService::DETAIL_LIMIT)
            ->get();

        $groups = $this->legalMetrics->aggregateByDimension($legalizacoes, $column);
        $asOf = now()->toIso8601String();
        $rows = [];
        foreach ($groups as $label => $group) {
            $rows[] = [
                'dataset' => 'legalizacoes',
                'dimension' => $dimension,
                'label' => $label,
                'count' => $group['count'],
                'sum_valor' => null,
                'sum_custo_planejado' => in_array('sum_custo_planejado', $metrics, true) ? $group['sum_custo_planejado'] : null,
                'sum_custo_realizado' => in_array('sum_custo_realizado', $metrics, true) ? $group['sum_custo_realizado'] : null,
                'avg_critical_days' => in_array('avg_critical_days', $metrics, true) ? $group['avg_critical_days'] : null,
                'sum_critical_days' => in_array('sum_critical_days', $metrics, true) ? $group['sum_critical_days'] : null,
                'as_of' => $asOf,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($rows, 0, ReportCatalogService::AGGREGATE_LIMIT);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $metrics
     * @return list<array{label: string, value: float, percent: float}>
     */
    private function buildChartBars(array $rows, array $metrics): array
    {
        $valueKey = in_array('sum_valor', $metrics, true) ? 'sum_valor' : 'count';
        if (in_array('sum_custo_realizado', $metrics, true)) {
            $valueKey = 'sum_custo_realizado';
        } elseif (in_array('sum_custo_planejado', $metrics, true)) {
            $valueKey = 'sum_custo_planejado';
        }

        $values = [];
        foreach ($rows as $row) {
            $values[] = [
                'label' => (string) ($row['label'] ?? '—'),
                'value' => (float) ($row[$valueKey] ?? 0),
            ];
        }
        $max = 0.0;
        foreach ($values as $item) {
            $max = max($max, $item['value']);
        }
        if ($max <= 0) {
            $max = 1.0;
        }

        return array_map(
            static fn (array $item): array => [
                'label' => $item['label'],
                'value' => $item['value'],
                'percent' => round(($item['value'] / $max) * 100, 1),
            ],
            array_slice($values, 0, 12),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function storeCsv(string $path, array $sections): void
    {
        $csv = fopen('php://temp', 'r+');
        if ($csv === false) {
            throw new RuntimeException('Não foi possível preparar o relatório.');
        }

        foreach ($sections as $index => $section) {
            if ($index > 0) {
                fputcsv($csv, [], ';');
            }

            fputcsv($csv, [
                'section',
                (string) ($section['dataset'] ?? ''),
                (string) ($section['mode'] ?? 'aggregate'),
                (string) ($section['dataset_label'] ?? ''),
            ], ';');

            if (($section['mode'] ?? '') === ReportCatalogService::MODE_DETAIL) {
                /** @var list<string> $columns */
                $columns = $section['columns'] ?? [];
                fputcsv($csv, $columns, ';');
                foreach ($section['rows'] ?? [] as $row) {
                    $line = [];
                    foreach ($columns as $column) {
                        $line[] = is_array($row) ? ($row[$column] ?? null) : null;
                    }
                    fputcsv($csv, $line, ';');
                }

                continue;
            }

            $metrics = $section['metrics'] ?? ['count'];
            $headers = ['dataset', 'dimension', 'label', 'count'];
            foreach (['sum_valor', ...self::LEGAL_RICH_METRICS] as $metric) {
                if (in_array($metric, $metrics, true)) {
                    $headers[] = $metric;
                }
            }
            $headers[] = 'as_of';
            fputcsv($csv, $headers, ';');

            foreach ($section['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $line = [
                    $row['dataset'] ?? '',
                    $row['dimension'] ?? '',
                    $row['label'] ?? '',
                    $row['count'] ?? 0,
                ];
                foreach (['sum_valor', ...self::LEGAL_RICH_METRICS] as $metric) {
                    if (in_array($metric, $metrics, true)) {
                        $line[] = $row[$metric] ?? null;
                    }
                }
                $line[] = $row['as_of'] ?? '';
                fputcsv($csv, $line, ';');
            }
        }

        rewind($csv);
        $contents = stream_get_contents($csv);
        fclose($csv);
        if (! is_string($contents)) {
            throw new RuntimeException('Não foi possível finalizar o relatório.');
        }

        Storage::disk('s3')->put($path, $contents, ['visibility' => 'private']);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function storeExcel(string $path, array $sections): void
    {
        $stored = Excel::store(
            new ReportRunWorkbookExport($sections),
            $path,
            's3',
            ExcelWriter::XLSX,
            ['visibility' => 'private'],
        );

        if (! $stored) {
            throw new RuntimeException('Não foi possível armazenar a planilha do relatório.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>  $definition
     */
    private function storePdf(ReportRun $run, string $path, array $sections, array $definition): void
    {
        $filters = $run->filters ?? [];
        $filtersLabel = collect($filters)
            ->map(static fn (mixed $value, string $key): string => $key.'='.(is_scalar($value) ? (string) $value : json_encode($value)))
            ->implode(', ');

        $asOf = now()->toIso8601String();
        foreach ($sections as $section) {
            $first = $section['rows'][0] ?? null;
            if (is_array($first) && isset($first['as_of']) && is_string($first['as_of'])) {
                $asOf = $first['as_of'];
                break;
            }
        }

        $charts = $definition['charts'] ?? ['table'];
        $showBars = in_array('bar', is_array($charts) ? $charts : [], true)
            || in_array('line', is_array($charts) ? $charts : [], true);

        Pdf::view('exports.report-builder-pdf', [
            'title' => $run->template?->name ?? 'Relatório personalizado',
            'sections' => $sections,
            'filtersLabel' => $filtersLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'asOf' => $asOf,
            'requestedBy' => $run->requester?->name,
            'showBars' => $showBars,
        ])
            ->format('a4')
            ->landscape()
            ->withBrowsershot(function ($browsershot): void {
                $this->terrenoExport->applyBrowsershotDefaults($browsershot);
            })
            ->disk('s3')
            ->save($path);
    }

    private function notifyScheduleIfNeeded(ReportRun $run): void
    {
        if ($run->report_schedule_id === null || $run->status !== 'completed') {
            return;
        }

        $schedule = $run->schedule;
        if ($schedule === null) {
            $schedule = ReportSchedule::query()->find($run->report_schedule_id);
        }
        if ($schedule === null || ! $schedule->notify_email) {
            return;
        }

        $user = $run->requester ?? User::query()->find($run->requested_by);
        if ($user === null) {
            return;
        }

        $user->notify(new ReportScheduleReadyNotification($run, $schedule->name));
    }

    private function queryFor(string $dataset): Builder
    {
        $query = DB::table($this->catalog->tableFor($dataset));
        if ($this->catalog->hasSoftDeletes($dataset)) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, string $dataset, array $filters): void
    {
        $statusColumn = match ($dataset) {
            'terrenos' => 'workflow_status_code',
            'deal_aprovacoes' => 'decision',
            default => 'status',
        };
        if (isset($filters['status']) && is_string($filters['status'])) {
            $query->where($statusColumn, $filters['status']);
        }
        if ($dataset === 'terrenos' && isset($filters['estado']) && is_string($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }
        if (isset($filters['date_from']) && is_string($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && is_string($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * @param  list<string>  $requested
     */
    private function resolveDimension(string $dataset, array $requested): string
    {
        $allowed = $this->catalog->dimensionKeysFor($dataset);
        foreach ($requested as $dimension) {
            if (in_array($dimension, $allowed, true)) {
                return $dimension;
            }
        }

        return $allowed[0] ?? 'status';
    }

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    private function resolveMetrics(string $dataset, array $requested): array
    {
        $allowed = $this->catalog->metricKeysFor($dataset);
        $metrics = array_values(array_intersect($requested, $allowed));
        if ($metrics === []) {
            $metrics = in_array('count', $allowed, true) ? ['count'] : array_slice($allowed, 0, 1);
        }

        return $metrics;
    }

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    private function resolveColumns(string $dataset, array $requested): array
    {
        $allowed = $this->catalog->columnKeysFor($dataset);
        $columns = array_values(array_intersect($requested, $allowed));
        if ($columns === []) {
            return array_slice($allowed, 0, 8);
        }

        return $columns;
    }
}
