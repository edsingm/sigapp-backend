<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\TenantExportType;
use App\Exports\Tenant\TerrenosExport;
use App\Models\Tenant\TenantExportGeneration;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeService;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

class TenantExportGenerator
{
    private const STORAGE_DISK = 's3';

    public function __construct(
        private readonly TerrenoExportService $terrenos,
        private readonly ViabilidadeService $viabilidades,
    ) {}

    /**
     * @return array{storage_disk: string, storage_path: string, file_name: string, mime_type: string, size: int}
     */
    public function generate(TenantExportGeneration $generation): array
    {
        $fileName = $this->fileName($generation);
        $path = 'exports/'.$generation->id.'/'.$fileName;

        match ($generation->type) {
            TenantExportType::TERRENOS_PDF => $this->generateTerrenosPdf($generation, $path),
            TenantExportType::TERRENOS_EXCEL => $this->generateTerrenosExcel($generation, $path),
            TenantExportType::TERRENO_DETAIL_PDF => $this->generateTerrenoDetailPdf($generation, $path),
            TenantExportType::TERRENO_CHECKLIST_PDF => $this->generateTerrenoChecklistPdf($generation, $path),
            TenantExportType::VIABILIDADE_PDF => $this->generateViabilidadePdf($generation, $path),
        };

        $disk = Storage::disk(self::STORAGE_DISK);
        if (! $disk->exists($path)) {
            throw new RuntimeException('A geração terminou sem produzir o arquivo esperado.');
        }

        return [
            'storage_disk' => self::STORAGE_DISK,
            'storage_path' => $path,
            'file_name' => $fileName,
            'mime_type' => $generation->type->mimeType(),
            'size' => $disk->size($path),
        ];
    }

    private function generateTerrenosPdf(TenantExportGeneration $generation, string $path): void
    {
        $filters = $generation->filters ?? [];
        $tenantId = (string) (tenant('id') ?? 'central');
        $terrenos = $this->terrenos->getTerrenosForExport($filters, $tenantId);
        $data = $this->terrenos->buildExportData($terrenos, $filters);

        Pdf::view('exports.terreno-pdf', $data)
            ->format('a4')
            ->landscape()
            ->withBrowsershot(function ($browsershot): void {
                $this->terrenos->applyBrowsershotDefaults($browsershot);
            })
            ->disk(self::STORAGE_DISK)
            ->save($path);
    }

    private function generateTerrenosExcel(TenantExportGeneration $generation, string $path): void
    {
        $stored = Excel::store(
            new TerrenosExport($generation->filters ?? []),
            $path,
            self::STORAGE_DISK,
            ExcelWriter::XLSX,
            ['visibility' => 'private'],
        );

        if (! $stored) {
            throw new RuntimeException('Não foi possível armazenar a planilha.');
        }
    }

    private function generateTerrenoDetailPdf(TenantExportGeneration $generation, string $path): void
    {
        $terreno = $this->terrenos->getTerrenoForSingleExport($this->subjectId($generation));
        if ($terreno === null) {
            throw new RuntimeException('Terreno não encontrado para exportação.');
        }

        $data = $this->terrenos->buildSingleExportData($terreno, $generation->requester?->name);

        Pdf::view('exports.terreno-detalhe-pdf', $data)
            ->format('a4')
            ->margins(5, 5, 5, 5)
            ->withBrowsershot(function ($browsershot): void {
                $this->terrenos->applyBrowsershotDefaults($browsershot);
                $browsershot->waitUntilNetworkIdle()->delay(2000);
            })
            ->disk(self::STORAGE_DISK)
            ->save($path);
    }

    private function generateTerrenoChecklistPdf(TenantExportGeneration $generation, string $path): void
    {
        $terreno = $this->terrenos->getTerrenoForChecklist($this->subjectId($generation));
        if ($terreno === null) {
            throw new RuntimeException('Terreno não encontrado para exportação.');
        }

        Pdf::view('exports.checklist-fechamento-pdf', [
            'terreno' => $terreno,
            'extraData' => $generation->payload ?? [],
            'dataGeracao' => now()->format('d/m/Y H:i'),
        ])
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->withBrowsershot(function ($browsershot): void {
                $this->terrenos->applyBrowsershotDefaults($browsershot);
            })
            ->disk(self::STORAGE_DISK)
            ->save($path);
    }

    private function generateViabilidadePdf(TenantExportGeneration $generation, string $path): void
    {
        $data = $this->viabilidades->exportData($this->subjectId($generation));

        Pdf::view('exports.viabilidade-pdf', $data)
            ->format('a4')
            ->withBrowsershot(function ($browsershot): void {
                $this->terrenos->applyBrowsershotDefaults($browsershot);
            })
            ->disk(self::STORAGE_DISK)
            ->save($path);
    }

    private function subjectId(TenantExportGeneration $generation): int
    {
        if ($generation->subject_id === null) {
            throw new RuntimeException('A exportação exige um recurso de origem.');
        }

        return $generation->subject_id;
    }

    private function fileName(TenantExportGeneration $generation): string
    {
        $date = now()->format('Y-m-d');

        return match ($generation->type) {
            TenantExportType::TERRENOS_PDF => "listagem-terrenos-{$date}.pdf",
            TenantExportType::TERRENOS_EXCEL => "listagem-terrenos-{$date}.xlsx",
            TenantExportType::TERRENO_DETAIL_PDF => "detalhe-terreno-{$generation->subject_id}-{$date}.pdf",
            TenantExportType::TERRENO_CHECKLIST_PDF => "checklist-terreno-{$generation->subject_id}-{$date}.pdf",
            TenantExportType::VIABILIDADE_PDF => "viabilidade-{$generation->subject_id}-{$date}.pdf",
        };
    }
}
