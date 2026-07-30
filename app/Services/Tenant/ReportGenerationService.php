<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ReportRun;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportGenerationService
{
    public function __construct(
        private readonly StorageQuotaService $storageQuota,
    ) {}

    /** @var array<string, array<string, string>> */
    private const DIMENSION_COLUMNS = [
        'terrenos' => ['status' => 'workflow_status_code', 'workflow_status_code' => 'workflow_status_code', 'estado' => 'estado', 'created_at' => 'created_at'],
        'viabilidades' => ['status' => 'status', 'workflow_status_code' => 'status', 'estado' => 'created_at', 'created_at' => 'created_at'],
        'comites' => ['status' => 'status', 'workflow_status_code' => 'status', 'estado' => 'created_at', 'created_at' => 'created_at'],
        'legalizacoes' => ['status' => 'status', 'workflow_status_code' => 'status', 'estado' => 'created_at', 'created_at' => 'created_at'],
    ];

    public function generate(ReportRun $run): void
    {
        $dataset = (string) ($run->definition_snapshot['datasets'][0] ?? 'terrenos');
        $dimension = (string) ($run->definition_snapshot['dimensions'][0] ?? 'status');
        $column = self::DIMENSION_COLUMNS[$dataset][$dimension] ?? self::DIMENSION_COLUMNS[$dataset]['status'];

        $query = $this->queryFor($dataset);
        $this->applyFilters($query, $dataset, $run->filters ?? []);
        $groups = $query->select([$column, DB::raw('count(*) as total')])
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(500)
            ->get();

        $csv = fopen('php://temp', 'r+');
        if ($csv === false) {
            throw new \RuntimeException('Não foi possível preparar o relatório.');
        }
        fputcsv($csv, ['dataset', 'dimension', 'label', 'total', 'as_of'], ';');
        $asOf = now()->toIso8601String();
        foreach ($groups as $group) {
            fputcsv($csv, [$dataset, $dimension, (string) ($group->{$column} ?? 'Não informado'), (int) $group->total, $asOf], ';');
        }
        rewind($csv);
        $contents = stream_get_contents($csv);
        fclose($csv);
        if (! is_string($contents)) {
            throw new \RuntimeException('Não foi possível finalizar o relatório.');
        }

        $path = 'reports/runs/'.$run->id.'.csv';
        Storage::disk('s3')->put($path, $contents, ['visibility' => 'private']);
        $size = $this->storageQuota->assertGeneratedFileFits('s3', $path);
        $run->update([
            'status' => 'completed',
            'progress' => 100,
            'storage_disk' => 's3',
            'storage_path' => $path,
            'mime_type' => 'text/csv',
            'size' => $size,
            'completed_by' => $run->requested_by,
            'completed_at' => now(),
        ]);
    }

    private function queryFor(string $dataset): Builder
    {
        $table = match ($dataset) {
            'viabilidades' => 'viabilidades',
            'comites' => 'comite_revisoes',
            'legalizacoes' => 'legalizacoes',
            default => 'terrenos',
        };

        return DB::table($table)->whereNull('deleted_at');
    }

    private function applyFilters(Builder $query, string $dataset, array $filters): void
    {
        $statusColumn = $dataset === 'terrenos' ? 'workflow_status_code' : 'status';
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
}
