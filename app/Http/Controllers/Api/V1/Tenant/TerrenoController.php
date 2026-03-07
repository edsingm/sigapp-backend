<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Services\Tenant\TerrenoFilterService;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\tenant\TerrenoResource;
use App\Http\Resources\tenant\TerrenoInfoResource;
use App\Http\Requests\Tenant\StoreTerrenoRequest;
use App\Http\Requests\Tenant\UpdateTerrenoRequest;
use App\Models\Tenant\TerrenoInfos;
use Illuminate\Support\Facades\Gate;

class TerrenoController extends Controller
{
    /**
     * List all terrenos.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenantId = tenant('id') ?? 'central';
        $forceRefresh = $request->boolean('force_refresh', false);
        $filters = $request->only(['search', 'per_page', 'page']);
        
        $cacheKey = "tenant:{$tenantId}:terrenos:index:" . md5(json_encode($filters));
        $cacheStore = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terrenos"]);

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
     * Create a new terreno.
     * (Middleware EnforcePlanLimits will check the limit before this)
     */
    public function store(StoreTerrenoRequest $request)
    {
        Gate::authorize('create', Terreno::class);

        $tenant = tenant();
        $limitService = new \App\Services\LimitEnforcementService($tenant);

        if (!$limitService->canCreateTerreno()) {
            return ApiResponseService::error(
                'LIMIT_EXCEEDED',
                'Limite de terrenos atingido para o seu plano.',
                null,
                403
            );
        }

        $validated = $request->validated();

        $validated['created_by'] = $request->user()->id;

        $terreno = Terreno::create($validated);

        return ApiResponseService::created(
            $terreno,
            'Terreno criado com sucesso'
        );
    }

    /**
     * Get a specific terreno.
     */
    public function show(string $id)
    {
        $terreno = Terreno::find($id);

        if (!$terreno) {
            return ApiResponseService::notFound('Terreno não encontrado');
        }

        $this->authorize('view', $terreno);

        return ApiResponseService::success($terreno);
    }

    /**
     * Update a terreno.
     */
    public function update(UpdateTerrenoRequest $request, string $id)
    {
        $terreno = Terreno::find($id);

        if (!$terreno) {
            return ApiResponseService::notFound('Terreno não encontrado');
        }

        $this->authorize('update', $terreno);

        $validated = $request->validated();

        $validated['updated_by'] = $request->user()->id;

        $terreno->update($validated);

        return ApiResponseService::success($terreno, 'Terreno atualizado com sucesso');
    }

    /**
     * Delete a terreno.
     */
    public function destroy(string $id)
    {
        $terreno = Terreno::find($id);

        if (!$terreno) {
            return ApiResponseService::notFound('Terreno não encontrado');
        }

        $this->authorize('delete', $terreno);

        $terreno->delete();

        return ApiResponseService::noContent();
    }

    public function filter(FilterTerrenosRequest $request, TerrenoFilterService $service)
    {
        Gate::authorize('viewAny', Terreno::class);
        try {
            $tenantId = tenant('id') ?? 'central';
            $forceRefresh = $request->boolean('force_refresh', false);
            $filters = $request->except(['force_refresh']);
            $cacheKey = "tenant:{$tenantId}:terrenos:filter:" . md5(json_encode($filters));
            $cacheStore = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terrenos"]);
            $resolver = fn() => $service->filter($request);

            if ($forceRefresh) {
                $cacheStore->forget($cacheKey);
                $paginator = $resolver();
                $cacheStore->put($cacheKey, $paginator, now()->addMinutes(30));
            } else {
                $paginator = $cacheStore->remember($cacheKey, now()->addMinutes(30), $resolver);
            }

            return $this->respondWithPagination($paginator, TerrenoResource::class);
        } catch (\Exception $e) {
            Log::error('Erro ao filtrar terrenos: ' . $e->getMessage(), [
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erro ao buscar terrenos',
                'error' => config('app.debug') ? $e->getMessage() : 'Ocorreu um erro interno'
            ], 500);
        }
    }

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

    public function forSelect()
    {
        Gate::authorize('viewAny', Terreno::class);

        $terrenos = Terreno::select('id', 'nome')
            ->orderBy('nome')
            ->get();

        return response()->json($terrenos);
    }
}
