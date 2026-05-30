<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Exports\Tenant\TerrenosExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Models\Tenant\Terreno;
use App\Services\Tenant\TerrenoExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;

class TerrenosExportController extends Controller
{
    public function __construct(
        private readonly TerrenoExportService $exportService,
    ) {}

    public function exportPdf(FilterTerrenosRequest $request): mixed
    {
        Gate::authorize('export', Terreno::class);

        $tenantId = tenant('id') ?? 'central';
        $validated = $request->validated();
        $terrenos = $this->exportService->getTerrenosForExport($validated, $tenantId);
        $data = $this->exportService->buildExportData($terrenos, $validated);

        return Pdf::view('exports.terreno-pdf', $data)
            ->format('a4')
            ->landscape()
            ->withBrowsershot(function ($browsershot) {
                $this->exportService->applyBrowsershotDefaults($browsershot);
            })
            ->name('listagem-terrenos-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportSinglePdf(int $id): mixed
    {
        Gate::authorize('export', Terreno::class);

        $terreno = $this->exportService->getTerrenoForSingleExport($id);

        if (! $terreno) {
            return response()->json(['message' => 'Terreno não encontrado'], 404);
        }

        $data = $this->exportService->buildSingleExportData($terreno, Auth::user()?->name);

        return Pdf::view('exports.terreno-detalhe-pdf', $data)
            ->format('a4')
            ->margins(5, 5, 5, 5)
            ->withBrowsershot(function ($browsershot) {
                $this->exportService->applyBrowsershotDefaults($browsershot);
                $browsershot->waitUntilNetworkIdle()
                    ->delay(2000);
            })
            ->name('detalhe-terreno-'.$terreno->getKey().'-'.Str::slug((string) $terreno->getAttribute('nome')).'.pdf');
    }

    public function exportExcel(FilterTerrenosRequest $request): mixed
    {
        Gate::authorize('export', Terreno::class);

        $filters = $request->validated();

        return Excel::download(new TerrenosExport($filters), 'listagem-terrenos-'.now()->format('Y-m-d').'.xlsx');
    }

    public function checklistPdf(Request $request, int $id): mixed
    {
        Gate::authorize('export', Terreno::class);

        try {
            Log::info("Iniciando geração de checklist PDF para o terreno ID: $id");

            $terreno = $this->exportService->getTerrenoForChecklist($id);

            if (! $terreno) {
                return response()->json(['message' => 'Terreno não encontrado'], 404);
            }

            $extraData = $request->only([
                'status',
                'observacoes',
                'checklist',
                'responsavel',
                'data',
            ]);
            Log::info('Dados extras recebidos:', $extraData);

            $data = [
                'terreno' => $terreno,
                'extraData' => $extraData,
                'dataGeracao' => now()->format('d/m/Y H:i'),
            ];

            return Pdf::view('exports.checklist-fechamento-pdf', $data)
                ->format('a4')
                ->margins(10, 10, 10, 10)
                ->withBrowsershot(function ($browsershot) {
                    $this->exportService->applyBrowsershotDefaults($browsershot);
                })
                ->name('checklist-'.$terreno->getKey().'-'.Str::slug((string) $terreno->getAttribute('nome')).'.pdf')
                ->toResponse(request());
        } catch (\Exception $e) {
            Log::error('Erro ao gerar checklist PDF: '.$e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro interno ao gerar o checklist.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
