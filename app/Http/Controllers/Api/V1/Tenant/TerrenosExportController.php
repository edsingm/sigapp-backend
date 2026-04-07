<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Exports\Tenant\TerrenosExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Models\Tenant\Terreno;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;

class TerrenosExportController extends Controller
{
    /**
     * Resolver o caminho do executável do Chrome para o Browsershot.
     */
    private function resolveChromePath(): ?string
    {
        $candidates = array_filter([
            env('BROWSERSHOT_CHROME_PATH'),
            env('PUPPETEER_EXECUTABLE_PATH'),
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Aplicar configurações padrão ao Browsershot.
     */
    private function applyBrowsershotDefaults($browsershot): void
    {
        $chromePath = $this->resolveChromePath();
        if ($chromePath) {
            $browsershot->setChromePath($chromePath);
        }

        $browsershot->noSandbox();
    }

    /**
     * Exportar a listagem de terrenos para PDF.
     */
    public function exportPdf(FilterTerrenosRequest $request)
    {
        Gate::authorize('export', Terreno::class);

        $tenantId = tenant('id') ?? 'central';
        $filtros = $request->all();
        $cacheKey = "tenant:{$tenantId}:terrenos:export:pdf:".md5(json_encode($filtros));

        $terrenos = Cache::tags(["tenant:{$tenantId}:terrenos"])->remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            // Buscar áreas com os mesmos filtros, mas sem paginação
            $query = Terreno::query()
                ->with([
                    'status',
                    'responsavel',
                    'regional',
                    'cidade',
                ])
                ->withSum('terrenoProdutos as total_unidades', 'unidades');

            // Aplicar filtros manualmente (similar ao AreaFilterService, mas sem paginação)
            $nome = $request->input('nome');
            if ($nome !== null && $nome !== '') {
                $query->whereRaw('LOWER(nome) LIKE ?', [Str::lower($nome).'%']);
            }

            $workflowStatuses = $request->input('workflow_statuses');
            if (is_array($workflowStatuses) && count($workflowStatuses)) {
                $query->whereIn('workflow_status_code', $workflowStatuses);
            }

            $ufs = $request->input('ufs');
            if (is_array($ufs) && count($ufs)) {
                $query->whereIn('estado', $ufs);
            }

            $cidades = $request->input('cidades');
            if (is_array($cidades) && count($cidades)) {
                $query->whereIn('cidade_code', $cidades);
            }

            $gestores = $request->input('gestor_ids');
            if (is_array($gestores) && count($gestores)) {
                $query->whereIn('responsavel_id', $gestores);
            }

            $corretores = $request->input('corretor_ids');
            if (is_array($corretores) && count($corretores)) {
                $query->whereIn('corretor_id', $corretores);
            }

            $regionais = $request->input('regional_ids');
            if (is_array($regionais) && count($regionais)) {
                $query->whereIn('regional_id', $regionais);
            }

            $dateField = $request->input('date_field');
            if (empty($dateField)) {
                $dateField = 'created_at';
            }
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');
            if ($dataInicio && $dataFim) {
                $query->whereBetween($dateField, [$dataInicio, $dataFim]);
            }

            $ano = $request->input('ano');
            if ($ano) {
                $query->whereYear($dateField, (int) $ano);
            }

            $query->orderBy('created_at', 'desc');

            return $query->get();
        });

        $data = [
            'terrenos' => $terrenos,
            'totalTerrenos' => $terrenos->count(),
            'dataGeracao' => now()->format('d/m/Y H:i'),
            'filtros' => [
                'nome' => $request->input('nome'),
                'dataInicio' => $request->input('data_inicio'),
                'dataFim' => $request->input('data_fim'),
                'workflow_statuses' => collect($request->input('workflow_statuses', []))
                    ->map(fn (string $code) => LandWorkflowService::statuses()[$code]['label'] ?? $code)
                    ->values()
                    ->all(),
            ],
        ];

        return Pdf::view('exports.terreno-pdf', $data)
            ->format('a4')
            ->landscape()
            ->withBrowsershot(function ($browsershot) {
                $this->applyBrowsershotDefaults($browsershot);
            })
            ->name('listagem-terrenos-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Exportar os detalhes de um único terreno para PDF.
     */
    public function exportSinglePdf($id)
    {
        Gate::authorize('export', Terreno::class);

        $terreno = Terreno::with([
            'status',
            'responsavel',
            'regional',
            'cidade',
            'terrenoProdutos.produto',
            'informacoes.createdBy',
            'proprietarios',
        ])
            ->withSum('terrenoProdutos as total_unidades', 'unidades')
            ->findOrFail($id);

        // Calcular VGV
        $vgvEstimado = $terreno->terrenoProdutos->reduce(function ($acc, $p) {
            return $acc + ($p->unidades * ($p->valor ?? 0));
        }, 0);

        $data = [
            'terreno' => $terreno,
            'vgvEstimado' => $vgvEstimado,
            'dataGeracao' => now()->format('d/m/Y H:i'),
            'geradoPor' => Auth::user()?->name ?? 'Sistema',
        ];

        return Pdf::view('exports.terreno-detalhe-pdf', $data)
            ->format('a4')
            ->margins(5, 5, 5, 5) // Margens em mm
            ->withBrowsershot(function ($browsershot) {
                $this->applyBrowsershotDefaults($browsershot);
                $browsershot->waitUntilNetworkIdle()
                    ->delay(2000);
            })
            ->name('detalhe-terreno-'.$terreno->id.'-'.Str::slug($terreno->nome).'.pdf');
    }

    /**
     * Exportar a listagem de terrenos para Excel.
     */
    public function exportExcel(FilterTerrenosRequest $request)
    {
        Gate::authorize('export', Terreno::class);

        $filters = [
            'nome' => $request->input('nome'),
            'workflow_statuses' => $request->input('workflow_statuses'),
            'ufs' => $request->input('ufs'),
            'cidades' => $request->input('cidades'),
            'gestor_ids' => $request->input('gestor_ids'),
            'corretor_ids' => $request->input('corretor_ids'),
            'regional_ids' => $request->input('regional_ids'),
            'date_field' => $request->input('date_field'),
            'data_inicio' => $request->input('data_inicio'),
            'data_fim' => $request->input('data_fim'),
            'ano' => $request->input('ano'),
        ];

        return Excel::download(new TerrenosExport($filters), 'listagem-terrenos-'.now()->format('Y-m-d').'.xlsx');
    }

    public function checklistPdf(Request $request, $id)
    {
        Gate::authorize('export', Terreno::class);

        try {
            Log::info("Iniciando geração de checklist PDF para o terreno ID: $id");

            $terreno = Terreno::with([
                'status',
                'responsavel',
                'regional',
                'cidade',
                'terrenoProdutos.produto',
                'proprietarios',
                'corretorExterno',
            ])->findOrFail($id);

            $extraData = $request->all();
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
                    $this->applyBrowsershotDefaults($browsershot);
                })
                ->name('checklist-'.$terreno->id.'-'.Str::slug($terreno->nome).'.pdf');
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
