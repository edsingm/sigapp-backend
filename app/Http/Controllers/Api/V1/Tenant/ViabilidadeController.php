<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ActivateViabilidadeRequest;
use App\Http\Requests\Tenant\CompareViabilidadesRequest;
use App\Http\Requests\Tenant\DecideViabilidadeApprovalRequest;
use App\Http\Requests\Tenant\DestroyViabilidadeRequest;
use App\Http\Requests\Tenant\DuplicateViabilidadeRequest;
use App\Http\Requests\Tenant\RecalculateViabilidadeRequest;
use App\Http\Requests\Tenant\RestoreViabilidadeRequest;
use App\Http\Requests\Tenant\SubmitViabilidadeApprovalRequest;
use App\Http\Requests\Tenant\ViabilidadeRequest;
use App\Http\Resources\Tenant\ViabilidadeCalculationResource;
use App\Http\Resources\Tenant\ViabilidadeResource;
use App\Http\Resources\Tenant\ViabilidadeSelectResource;
use App\Models\Tenant\Viabilidade;
use App\Services\ApiResponseService;
use App\Services\Tenant\Viabilidade\ViabilidadeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPdf\Facades\Pdf;

class ViabilidadeController extends Controller
{
    public function __construct(
        private readonly ViabilidadeService $viabilidadeService,
    ) {}

    /**
     * Ativar uma viabilidade (mudar status de rascunho para ativo)
     */
    public function ativar(ActivateViabilidadeRequest $request, int $id): JsonResponse
    {
        return ApiResponseService::success(
            new ViabilidadeResource($this->viabilidadeService->ativar($id, $request->user())),
            'Viabilidade salva com sucesso'
        );
    }

    /**
     * Solicitar aprovação de uma viabilidade.
     */
    public function solicitarAprovacao(SubmitViabilidadeApprovalRequest $request, int $id): JsonResponse
    {
        $viabilidade = $this->viabilidadeService->solicitarAprovacao(
            $id,
            $request->validated('approval_notes'),
            $request->user(),
        );

        return ApiResponseService::success(
            new ViabilidadeResource($viabilidade),
            'Aprovação da viabilidade solicitada com sucesso'
        );
    }

    /**
     * Aprovar uma viabilidade.
     */
    public function aprovar(DecideViabilidadeApprovalRequest $request, int $id): JsonResponse
    {
        return $this->decidirAprovacao($request, $id, 'aprovada');
    }

    /**
     * Reprovar uma viabilidade.
     */
    public function reprovar(DecideViabilidadeApprovalRequest $request, int $id): JsonResponse
    {
        return $this->decidirAprovacao($request, $id, 'reprovada');
    }

    /**
     * Listar todas as viabilidades
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Viabilidade::class);

            $tenantId = tenant('id') ?? 'central';
            $filtros = $request->only(['search', 'terreno_id', 'per_page', 'page']);
            $cacheKey = "tenant:{$tenantId}:viabilidades:index:".md5(json_encode($filtros));

            $viabilidades = Cache::tags(["tenant:{$tenantId}:viabilidades"])->remember($cacheKey, now()->addMinutes(30), function () use ($filtros) {
                return $this->viabilidadeService->listarTodasViabilidades($filtros);
            });

            return ApiResponseService::paginated(
                $viabilidades->through(fn (Viabilidade $viabilidade): array => ViabilidadeResource::make($viabilidade)->resolve())
            );
        } catch (Exception $e) {
            Log::error('Erro no ViabilidadeController::index', ['error' => $e->getMessage()]);

            return ApiResponseService::serverError('Erro ao listar viabilidades');
        }
    }

    /**
     * Criar nova viabilidade e gerar DRE
     */
    public function store(ViabilidadeRequest $request): JsonResponse
    {
        try {
            $resultado = $this->viabilidadeService->criarViabilidadeComDre($request->validated(), $request->user());
            Log::info('Viabilidade criada com sucesso', $resultado);

            return ApiResponseService::created(
                new ViabilidadeCalculationResource($resultado),
                'Viabilidade criada com sucesso'
            );

        } catch (Exception $e) {
            Log::error('Erro no ViabilidadeController::store', [
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponseService::validationError([
                'viabilidade' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Buscar viabilidade com DRE por ID
     */
    public function show(int $id): JsonResponse
    {
        $viabilidade = $this->viabilidadeService->findOrFail($id);
        $this->authorize('view', $viabilidade);

        return ApiResponseService::success(
            new ViabilidadeCalculationResource($this->viabilidadeService->buscarViabilidadeComDre($viabilidade))
        );
    }

    /**
     * Atualizar viabilidade e recalcular DRE
     */
    public function update(ViabilidadeRequest $request, int $id): JsonResponse
    {
        try {
            $resultado = $this->viabilidadeService->atualizarViabilidadeComDre($id, $request->validated(), $request->user());

            return ApiResponseService::success(
                new ViabilidadeCalculationResource($resultado),
                'Viabilidade atualizada com sucesso'
            );

        } catch (Exception $e) {
            Log::error('Erro no ViabilidadeController::update', [
                'viabilidade_id' => $id,
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::validationError([
                'viabilidade' => [$e->getMessage()],
            ]);
        }
    }

    protected function decidirAprovacao(DecideViabilidadeApprovalRequest $request, int $id, string $decision): JsonResponse
    {
        $viabilidade = $this->viabilidadeService->decidirAprovacao(
            $id,
            $decision,
            $request->validated('approval_notes'),
            $request->user(),
        );

        return ApiResponseService::success(
            new ViabilidadeResource($viabilidade),
            $decision === 'aprovada'
                ? 'Viabilidade aprovada com sucesso'
                : 'Viabilidade reprovada com sucesso'
        );
    }

    /**
     * Listar viabilidades de uma área
     *
     * @param  int  $areaId
     */
    public function byTerreno(int $terrenoId): JsonResponse
    {
        try {
            $this->authorize('viewAny', Viabilidade::class);

            return ApiResponseService::success(
                ViabilidadeResource::collection($this->viabilidadeService->listarViabilidadesPorTerreno($terrenoId))
            );
        } catch (Exception $e) {

            Log::error('Erro no ViabilidadeController::byTerreno', [
                'terreno_id' => $terrenoId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao buscar viabilidades da área');
        }
    }

    /**
     * Buscar viabilidade mais recente de um terreno
     */
    public function latest(int $terrenoId): JsonResponse
    {
        try {
            $this->authorize('viewAny', Viabilidade::class);

            $viabilidade = $this->viabilidadeService->buscarViabilidadeAtual($terrenoId);

            if (! $viabilidade) {
                return ApiResponseService::notFound('Nenhuma viabilidade encontrada para este terreno');
            }

            return ApiResponseService::success(new ViabilidadeResource($viabilidade));
        } catch (Exception $e) {

            Log::error('Erro no ViabilidadeController::latest', [
                'terreno_id' => $terrenoId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao buscar viabilidade mais recente');
        }
    }

    /**
     * Duplicar viabilidade
     */
    public function duplicate(DuplicateViabilidadeRequest $request, int $id): JsonResponse
    {
        try {
            return ApiResponseService::created(
                new ViabilidadeResource($this->viabilidadeService->duplicarViabilidade($id, $request->user())),
                'Viabilidade duplicada com sucesso'
            );

        } catch (Exception $e) {
            Log::error('Erro no ViabilidadeController::duplicate', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::validationError([
                'viabilidade' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Excluir viabilidade
     */
    public function destroy(DestroyViabilidadeRequest $request, int $id): JsonResponse
    {
        try {
            $resultado = $this->viabilidadeService->excluirViabilidade($id);

            if ($resultado) {
                return ApiResponseService::success(null, 'Viabilidade excluída com sucesso');
            }

            return ApiResponseService::serverError('Erro ao excluir viabilidade');
        } catch (Exception $e) {

            Log::error('Erro no ViabilidadeController::destroy', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::validationError([
                'viabilidade' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Comparar duas viabilidades
     */
    public function compare(CompareViabilidadesRequest $request): JsonResponse
    {
        try {
            $comparacao = $this->viabilidadeService->compareByIds(
                (int) $request->validated('viabilidade_1_id'),
                (int) $request->validated('viabilidade_2_id'),
            );

            return ApiResponseService::success([
                'viabilidade_1' => (new ViabilidadeCalculationResource($comparacao['viabilidade_1']))->resolve(),
                'viabilidade_2' => (new ViabilidadeCalculationResource($comparacao['viabilidade_2']))->resolve(),
            ]);
        } catch (Exception $e) {

            Log::error('Erro no ViabilidadeController::compare', [
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::validationError([
                'viabilidade' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Gerar DRE para uma viabilidade específica
     */
    public function gerarDre(int $id): JsonResponse
    {
        try {
            $viabilidade = $this->viabilidadeService->findOrFail($id);
            $this->authorize('gerarDre', $viabilidade);

            $resultado = $this->viabilidadeService->buscarViabilidadeComDre($id);

            return ApiResponseService::success($resultado['dre_resultados']);
        } catch (Exception $e) {

            Log::error('Erro no ViabilidadeController::gerarDre', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao gerar DRE: '.$e->getMessage());
        }
    }

    /**
     * Recalcular DRE de uma viabilidade existente
     */
    public function recalcular(RecalculateViabilidadeRequest $request, int $id): JsonResponse
    {
        try {
            $resultado = $this->viabilidadeService->recalcularDre($id, $request->user());

            return ApiResponseService::success(
                new ViabilidadeCalculationResource($resultado),
                'Viabilidade recalculada com sucesso'
            );

        } catch (Exception $e) {
            Log::error('Erro no ViabilidadeController::recalcular', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponseService::validationError([
                'viabilidade' => ['Erro ao recalcular viabilidade: '.$e->getMessage()],
            ]);
        }
    }

    /**
     * Restaurar viabilidade excluída
     */
    public function restore(RestoreViabilidadeRequest $request, int $id): JsonResponse
    {
        try {
            return ApiResponseService::success(
                new ViabilidadeResource($this->viabilidadeService->restore($id)),
                'Viabilidade restaurada com sucesso'
            );
        } catch (Exception $e) {

            Log::error('Erro no ViabilidadeController::restore', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao restaurar viabilidade');
        }
    }

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
     * Exportar viabilidade para PDF
     *
     * @return mixed
     */
    public function exportPdf(int $id)
    {
        try {
            $viabilidade = $this->viabilidadeService->findOrFail($id);
            $this->authorize('export', $viabilidade);
            $data = $this->viabilidadeService->exportData($id);

            return Pdf::view('exports.viabilidade-pdf', $data)
                ->format('a4')
                ->withBrowsershot(function ($browsershot) {
                    $chromePath = $this->resolveChromePath();
                    if ($chromePath) {
                        $browsershot->setChromePath($chromePath);
                    }
                    $browsershot->noSandbox();
                })
                ->name("viabilidade-{$id}-".now()->format('Y-m-d').'.pdf')
                ->toResponse(request());

        } catch (Exception $e) {
            Log::error('Erro no ViabilidadeController::exportPdf', [
                'viabilidade_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar PDF: '.$e->getMessage(),
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Listar viabilidades para campos de seleção
     */
    public function forSelect(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Viabilidade::class);

            return ApiResponseService::success(
                ViabilidadeSelectResource::collection(
                    $this->viabilidadeService->forSelect($request->integer('terreno_id') ?: null)
                )
            );
        } catch (Exception $e) {

            Log::error('Erro no ViabilidadeController::forSelect', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao buscar viabilidades');
        }
    }
}
