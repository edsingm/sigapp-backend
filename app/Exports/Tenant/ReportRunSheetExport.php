<?php

declare(strict_types=1);

namespace App\Exports\Tenant;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportRunSheetExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /** @var list<string> */
    private const EXTRA_METRICS = [
        'sum_valor',
        'sum_custo_planejado',
        'sum_custo_realizado',
        'avg_critical_days',
        'sum_critical_days',
    ];

    /** @param array<string, mixed> $section */
    public function __construct(private readonly array $section) {}

    public function title(): string
    {
        $title = (string) ($this->section['dataset_label'] ?? $this->section['dataset'] ?? 'Seção');

        return Str::limit(str_replace(['*', ':', '/', '\\', '?', '[', ']'], '', $title), 31, '');
    }

    public function collection(): Collection
    {
        if (($this->section['mode'] ?? 'aggregate') === 'detail') {
            $columns = $this->section['columns'] ?? [];

            return collect($this->section['rows'] ?? [])->map(static function (array $row) use ($columns): array {
                return array_map(
                    static fn (string $column): mixed => $row[$column] ?? null,
                    $columns,
                );
            });
        }

        $metrics = $this->section['metrics'] ?? ['count'];

        return collect($this->section['rows'] ?? [])->map(function (array $row) use ($metrics): array {
            $mapped = [
                (string) ($row['dataset'] ?? ''),
                (string) ($row['dimension'] ?? ''),
                (string) ($row['label'] ?? ''),
                (int) ($row['count'] ?? 0),
            ];
            foreach (self::EXTRA_METRICS as $metric) {
                if (in_array($metric, $metrics, true)) {
                    $mapped[] = $row[$metric] ?? null;
                }
            }
            $mapped[] = (string) ($row['as_of'] ?? '');

            return $mapped;
        });
    }

    /** @return list<string> */
    public function headings(): array
    {
        if (($this->section['mode'] ?? 'aggregate') === 'detail') {
            /** @var list<string> $labels */
            $labels = $this->section['column_labels'] ?? ($this->section['columns'] ?? []);

            return $labels;
        }

        $headings = ['Dataset', 'Dimensão', 'Rótulo', 'Quantidade'];
        $metrics = $this->section['metrics'] ?? ['count'];
        $labels = [
            'sum_valor' => 'Soma valor',
            'sum_custo_planejado' => 'Custo planejado',
            'sum_custo_realizado' => 'Custo realizado',
            'avg_critical_days' => 'Média dias críticos',
            'sum_critical_days' => 'Soma dias críticos',
        ];
        foreach (self::EXTRA_METRICS as $metric) {
            if (in_array($metric, $metrics, true)) {
                $headings[] = $labels[$metric];
            }
        }
        $headings[] = 'As of';

        return $headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1A4DB5'],
                ],
            ],
        ];
    }
}
