<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TenantExportType;
use App\Exports\Tenant\TerrenosExport;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\Terreno;
use App\Services\Tenant\StorageQuotaService;
use App\Services\Tenant\TenantExportGenerator;
use App\Services\Tenant\TerrenoExportService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

final class TenantExportGeneratorTest extends TestCase
{
    public function test_generates_terrain_list_pdf_on_private_s3_path(): void
    {
        $generation = $this->generation(TenantExportType::TERRENOS_PDF);
        $path = 'exports/91/listagem-terrenos-'.now()->format('Y-m-d').'.pdf';
        $terrenos = new Collection;
        $service = $this->createMock(TerrenoExportService::class);
        $service->expects($this->once())
            ->method('getTerrenosForExport')
            ->with(['ufs' => ['SP']], 'central')
            ->willReturn($terrenos);
        $service->expects($this->once())
            ->method('buildExportData')
            ->with($terrenos, ['ufs' => ['SP']])
            ->willReturn([
                'terrenos' => $terrenos,
                'totalTerrenos' => 0,
                'dataGeracao' => '25/07/2026 12:00',
                'filtros' => [],
            ]);
        $viabilidades = $this->createMock(ViabilidadeService::class);
        $pdf = Pdf::fake();
        Storage::fake('s3');
        Storage::disk('s3')->put($path, 'pdf');

        $quota = $this->createMock(StorageQuotaService::class);
        $quota->expects($this->once())
            ->method('assertGeneratedFileFits')
            ->with('s3', $path)
            ->willReturn(3);
        $artifact = (new TenantExportGenerator($service, $viabilidades, $quota))->generate($generation);

        $pdf->assertViewIs('exports.terreno-pdf');
        $pdf->assertSaved($path);
        $this->assertSame($path, $artifact['storage_path']);
        $this->assertSame('application/pdf', $artifact['mime_type']);
        $this->assertSame(3, $artifact['size']);
    }

    public function test_generates_chunked_terrain_excel_on_private_s3_path(): void
    {
        $generation = $this->generation(TenantExportType::TERRENOS_EXCEL);
        $path = 'exports/91/listagem-terrenos-'.now()->format('Y-m-d').'.xlsx';
        $service = $this->createMock(TerrenoExportService::class);
        $viabilidades = $this->createMock(ViabilidadeService::class);
        Excel::fake();
        Storage::fake('s3');
        Storage::disk('s3')->put($path, 'xlsx');

        $quota = $this->createMock(StorageQuotaService::class);
        $quota->expects($this->once())
            ->method('assertGeneratedFileFits')
            ->with('s3', $path)
            ->willReturn(4);
        $artifact = (new TenantExportGenerator($service, $viabilidades, $quota))->generate($generation);

        Excel::assertStored(
            $path,
            's3',
            fn (TerrenosExport $export): bool => $export->query()->getModel()->getTable() === (new Terreno)->getTable(),
        );
        $this->assertSame($path, $artifact['storage_path']);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $artifact['mime_type'],
        );
        $this->assertSame(4, $artifact['size']);
    }

    private function generation(TenantExportType $type): TenantExportGeneration
    {
        $generation = new TenantExportGeneration;
        $generation->forceFill([
            'id' => 91,
            'type' => $type,
            'filters' => ['ufs' => ['SP']],
        ]);

        return $generation;
    }
}
