<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Http\Requests\Tenant\StoreTerrenoRequest;
use App\Http\Requests\Tenant\UpdateTerrenoRequest;
use App\Http\Requests\Tenant\UploadKmzRequest;
use App\Http\Resources\Tenant\TerrenoInfoResource;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoInfos;
use App\Services\ApiResponseService;
use App\Services\Tenant\KmzParserService;
use App\Services\Tenant\LandWorkflowService;
use App\Services\Tenant\TerrenoFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TerrenoController extends Controller
{
    public function __construct(
        protected LandWorkflowService $workflowService,
        protected KmzParserService $kmzParser,
    ) {}

    /**
     * Lista terrenos com filtros e cache.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenantId = tenant('id') ?? 'central';
        $forceRefresh = $request->boolean('force_refresh', false);
        $filters = $request->only(['search', 'per_page', 'page']);

        $cacheKey = "tenant:{$tenantId}:terrenos:index:".md5(json_encode($filters));
        $cacheStore = Cache::tags(["tenant:{$tenantId}:terrenos"]);

        $resolver = function () use ($request) {
            $query = Terreno::query();

            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where('nome', 'like', "%{$search}%");
            }

            $perPage = min($request->get('per_page', 15), 100);

            return ApiResponseService::paginated($query->paginate($perPage));
        };

        if ($forceRefresh) {
            $cacheStore->forget($cacheKey);
            $freshData = $resolver();
            $cacheStore->put($cacheKey, $freshData, now()->addMinutes(30));

            return $freshData;
        }

        return $cacheStore->remember($cacheKey, now()->addMinutes(30), $resolver);
    }

    /**
     * Cria um novo terreno.
     */
    public function store(StoreTerrenoRequest $request)
    {
        Gate::authorize('create', Terreno::class);

        $validated = $request->validated();
        unset($validated['workflow_status_code']);
        $validated['created_by'] = $request->user()->id;

        $terreno = Terreno::create($validated);
        $this->workflowService->initialize($terreno, $request->user());

        return ApiResponseService::created(
            new TerrenoResource($this->loadDetailRelations($terreno)),
            'Terreno criado com sucesso'
        );
    }

    /**
     * Exibe um terreno específico.
     */
    public function show(string $id)
    {
        $terreno = Terreno::find($id);

        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado');
        }

        $this->authorize('view', $terreno);

        return ApiResponseService::success(
            new TerrenoResource($this->loadDetailRelations($terreno))
        );
    }

    /**
     * Atualiza um terreno.
     */
    public function update(UpdateTerrenoRequest $request, string $id)
    {
        $terreno = Terreno::find($id);

        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado');
        }

        $this->authorize('update', $terreno);

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        $terreno->update($validated);
        $this->workflowService->syncReadiness($terreno, $request->user(), 'terrain_updated');

        return ApiResponseService::success(
            new TerrenoResource($this->loadDetailRelations($terreno)),
            'Terreno atualizado com sucesso'
        );
    }

    /**
     * Excluir um terreno.
     */
    public function destroy(string $id)
    {
        $terreno = Terreno::find($id);

        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado');
        }

        $this->authorize('delete', $terreno);
        $terreno->delete();

        return ApiResponseService::noContent();
    }

    /**
     * Filtrar terrenos com lógica avançada.
     */
    public function filter(FilterTerrenosRequest $request, TerrenoFilterService $service)
    {
        Gate::authorize('viewAny', Terreno::class);
        try {
            $tenantId = tenant('id') ?? 'central';
            $forceRefresh = $request->boolean('force_refresh', false);
            $filters = $request->except(['force_refresh']);
            $cacheKey = "tenant:{$tenantId}:terrenos:filter:".md5(json_encode($filters));
            $cacheStore = Cache::tags(["tenant:{$tenantId}:terrenos"]);
            $resolver = fn () => $service->filter($request);

            if ($forceRefresh) {
                $cacheStore->forget($cacheKey);
                $paginator = $resolver();
                $cacheStore->put($cacheKey, $paginator, now()->addMinutes(30));
            } else {
                $paginator = $cacheStore->remember($cacheKey, now()->addMinutes(30), $resolver);
            }

            return $this->respondWithPagination($paginator, TerrenoResource::class);
        } catch (\Exception $e) {
            Log::error('Erro ao filtrar terrenos: '.$e->getMessage(), [
                'params' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao buscar terrenos',
                'error' => config('app.debug') ? $e->getMessage() : 'Ocorreu um erro interno',
            ], 500);
        }
    }

    /**
     * Armazenar uma nova informação (nota) para o terreno.
     */
    public function storeInfo(Request $request, string $id)
    {
        $terreno = Terreno::findOrFail($id);
        $this->authorize('update', $terreno);

        $request->validate([
            'descricao' => 'required|string',
        ]);

        $info = $terreno->informacoes()->create([
            'descricao' => $request->descricao,
            'created_by' => $request->user()->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Nota adicionada com sucesso!',
            'data' => new TerrenoInfoResource($info->load('user')),
        ], 201);
    }

    /**
     * Obter todas as informações (notas) de um terreno.
     */
    public function getInformacoes(string $id)
    {
        $terreno = Terreno::findOrFail($id);
        $this->authorize('view', $terreno);

        $informacoes = $terreno->informacoes()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => TerrenoInfoResource::collection($informacoes),
        ]);
    }

    /**
     * Atualizar uma informação (nota) existente.
     */
    public function updateInfo(Request $request, string $infoId)
    {
        $info = TerrenoInfos::findOrFail($infoId);
        $terreno = $info->terreno;
        $this->authorize('update', $terreno);

        $request->validate([
            'descricao' => 'required|string',
        ]);

        $info->update([
            'descricao' => $request->descricao,
        ]);

        return response()->json([
            'message' => 'Informação atualizada com sucesso!',
            'data' => new TerrenoInfoResource($info->load('createdBy')),
        ]);
    }

    /**
     * Excluir uma informação (nota).
     */
    public function destroyInfo(string $infoId)
    {
        $info = TerrenoInfos::findOrFail($infoId);
        $terreno = $info->terreno;
        $this->authorize('update', $terreno);

        $info->delete();

        return response()->json([
            'message' => 'Informação removida com sucesso!',
        ], 204);
    }

    /**
     * Importa coordenadas de polígono a partir de um arquivo .kml ou .kmz.
     */
    public function importKmz(UploadKmzRequest $request, string $id)
    {
        $terreno = Terreno::find($id);

        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado');
        }

        $this->authorize('update', $terreno);

        try {
            $coords = $this->kmzParser->parse($request->file('arquivo'));
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['arquivo' => [$e->getMessage()]],
            ], 422);
        }

        $terreno->update([
            'polygon_coords' => $coords,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponseService::success(
            new TerrenoResource($this->loadDetailRelations($terreno)),
            'Coordenadas importadas com sucesso'
        );
    }

    /**
     * Listar terrenos para seleção.
     */
    public function forSelect()
    {
        Gate::authorize('viewAny', Terreno::class);

        $terrenos = Terreno::select('id', 'nome')
            ->orderBy('nome')
            ->get();

        return response()->json($terrenos);
    }

    /**
     * Carregar relações detalhadas para o recurso de terreno.
     */
    protected function loadDetailRelations(Terreno $terreno): Terreno
    {
        return $terreno->fresh([
            'responsavel',
            'corretorExterno',
            'regional',
            'cidade',
            'createdBy',
            'updatedBy',
            'proprietarios',
            'contatos',
            'documentos',
            'terrenoProdutos.produto',
            'viabilidades.createdBy',
            'viabilidades.approvalDecidedBy',
            'viabilidadeAtual.createdBy',
            'viabilidadeAtual.approvalDecidedBy',
            'viabilidadeAtual.secoes',
            'viabilidadeAtual.aprovacoes.user',
            'informacoes.user',
            'comiteAtual.viabilidade',
            'comiteAtual.pareceresDepartamento',
            'comiteAtual.pendencias',
            'negociacaoAtual.eventos',
            'contratoAtual.partes',
            'legalizacao.etapas',
            'legalizacao.pendencias',
            'tasks.assignedUser',
            'statusHistories',
            'activities',
        ]);
    }
}
