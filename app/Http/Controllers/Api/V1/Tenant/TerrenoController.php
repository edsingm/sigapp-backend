<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Http\Requests\Tenant\StoreTerrenoRequest;
use App\Http\Requests\Tenant\StoreTerrenoInfoRequest;
use App\Http\Requests\Tenant\UpdateTerrenoRequest;
use App\Http\Requests\Tenant\UpdateTerrenoInfoRequest;
use App\Http\Requests\Tenant\UploadKmzRequest;
use App\Http\Resources\Tenant\TerrenoInfoResource;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Tenant\TerrenoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TerrenoController extends Controller
{
    public function __construct(
        private readonly TerrenoService $service,
    ) {}

    /**
     * Lista terrenos com filtros e cache.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Terreno::class);
        $terrenos = $this->service
            ->listPaginated($request)
            ->through(fn (Terreno $terreno): array => TerrenoResource::make($terreno)->resolve());

        return ApiResponseService::paginated($terrenos);
    }

    /**
     * Cria um novo terreno.
     */
    public function store(StoreTerrenoRequest $request)
    {
        Gate::authorize('create', Terreno::class);
        $terreno = $this->service->create($request->validated(), $request->user());

        return ApiResponseService::created(
            new TerrenoResource($terreno),
            'Terreno criado com sucesso'
        );
    }

    /**
     * Exibe um terreno específico.
     */
    public function show(string $terreno)
    {
        $terreno = $this->service->findOrFail($terreno);
        $this->authorize('view', $terreno);

        return ApiResponseService::success(
            new TerrenoResource($this->service->show($terreno))
        );
    }

    /**
     * Atualiza um terreno.
     */
    public function update(UpdateTerrenoRequest $request, string $terreno)
    {
        $terreno = $this->service->findOrFail($terreno);
        $this->authorize('update', $terreno);
        $terreno = $this->service->update($terreno, $request->validated(), $request->user());

        return ApiResponseService::success(
            new TerrenoResource($terreno),
            'Terreno atualizado com sucesso'
        );
    }

    /**
     * Excluir um terreno.
     */
    public function destroy(string $terreno)
    {
        $terreno = $this->service->findOrFail($terreno);
        $this->authorize('delete', $terreno);
        $this->service->delete($terreno);

        return ApiResponseService::noContent();
    }

    /**
     * Filtrar terrenos com lógica avançada.
     */
    public function filter(FilterTerrenosRequest $request)
    {
        Gate::authorize('viewAny', Terreno::class);

        try {
            $paginator = $this->service->filter($request);

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
    public function storeInfo(StoreTerrenoInfoRequest $request, string $id)
    {
        $terreno = $this->service->findOrFail($id);
        $this->authorize('update', $terreno);
        $info = $this->service->storeInfo($terreno, (string) $request->validated('descricao'), $request->user());

        return ApiResponseService::created(
            new TerrenoInfoResource($info),
            'Nota adicionada com sucesso!'
        );
    }

    /**
     * Obter todas as informações (notas) de um terreno.
     */
    public function getInformacoes(string $id)
    {
        $terreno = $this->service->findOrFail($id);
        $this->authorize('view', $terreno);

        return ApiResponseService::success(
            TerrenoInfoResource::collection($this->service->listInfos($terreno))
        );
    }

    /**
     * Atualizar uma informação (nota) existente.
     */
    public function updateInfo(UpdateTerrenoInfoRequest $request, string $infoId)
    {
        $info = $this->service->findInfoOrFail($infoId);
        $terreno = $info->terreno;
        $this->authorize('update', $terreno);
        $info = $this->service->updateInfo($info, (string) $request->validated('descricao'));

        return ApiResponseService::success(
            new TerrenoInfoResource($info),
            'Informação atualizada com sucesso!'
        );
    }

    /**
     * Excluir uma informação (nota).
     */
    public function destroyInfo(string $infoId)
    {
        $info = $this->service->findInfoOrFail($infoId);
        $terreno = $info->terreno;
        $this->authorize('update', $terreno);
        $this->service->deleteInfo($info);

        return ApiResponseService::noContent();
    }

    /**
     * Importa coordenadas de polígono a partir de um arquivo .kml ou .kmz.
     */
    public function importKmz(UploadKmzRequest $request, string $id)
    {
        $terreno = $this->service->findOrFail($id);
        $this->authorize('update', $terreno);

        try {
            $terreno = $this->service->importPolygon($terreno, $request->file('arquivo'), $request->user());
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['arquivo' => [$e->getMessage()]],
            ], 422);
        }

        return ApiResponseService::success(
            new TerrenoResource($terreno),
            'Coordenadas importadas com sucesso'
        );
    }

    /**
     * Listar terrenos para seleção.
     */
    public function forSelect()
    {
        Gate::authorize('viewAny', Terreno::class);

        return ApiResponseService::success($this->service->listForSelect());
    }
}
