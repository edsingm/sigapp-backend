<?php

declare(strict_types=1);

namespace App\Exports\Tenant;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * @phpstan-import-type ReportSection from ReportRunSheetExport
 */
class ReportRunWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  list<ReportSection>  $sections
     */
    public function __construct(private readonly array $sections) {}

    /** @return list<ReportRunSheetExport> */
    public function sheets(): array
    {
        if ($this->sections === []) {
            return [new ReportRunSheetExport([
                'dataset' => 'vazio',
                'dataset_label' => 'Vazio',
                'mode' => 'aggregate',
                'metrics' => ['count'],
                'rows' => [],
            ])];
        }

        return array_map(
            static fn (array $section): ReportRunSheetExport => new ReportRunSheetExport($section),
            $this->sections,
        );
    }
}
