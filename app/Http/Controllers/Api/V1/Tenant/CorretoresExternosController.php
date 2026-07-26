<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyCorretorExternoRequest;
use App\Http\Requests\Tenant\ShowCorretorExternoRequest;
use App\Http\Requests\Tenant\StoreCorretorExternoRequest;
use App\Http\Requests\Tenant\UpdateCorretorExternoRequest;
use App\Http\Resources\Tenant\CorretorExternoResource;
use App\Repositories\Tenant\CorretorExternoRepository;
use App\Services\ApiResponseService;
use App\Services\Tenant\TenantCacheService;
use Illuminate\Http\JsonResponse;

class CorretoresExternosController extends Controller
{
    public function __construct(
        protected CorretorExternoRepository $repository,
        private readonly TenantCacheService $cache,
    ) {}

    /**
     * Listar os corretores externos.
     */
    public function index(): JsonResponse
    {
        $perPage = min(max(request()->integer('per_page', 10), 1), 100);
        $filters = request()->only(['search', 'page']);
        $cacheKey = $this->cache->key('corretores_externos', 'index', [
            ...$filters,
            'per_page' => $perPage,
        ]);

        $paginator = $this->cache->remember(
            'corretores_externos',
            $cacheKey,
            now()->addMinutes(30),
            fn () => $this->repository->paginate($perPage, $filters),
        );

        return $this->respondWithPagination($paginator, CorretorExternoResource::class);
    }

    /**
     * Armazenar um novo corretor externo.
     */
    public function store(StoreCorretorExternoRequest $request): JsonResponse
    {
        $corretor = $this->repository->create($request->validated());

        return ApiResponseService::created(
            new CorretorExternoResource($corretor),
            'Corretor externo criado com sucesso.'
        );
    }

    /**
     * Exibir os detalhes de um corretor externo específico.
     */
    public function show(ShowCorretorExternoRequest $request, string $id): JsonResponse
    {
        $corretor = $this->repository->findById($id);

        return ApiResponseService::success(
            new CorretorExternoResource($corretor)
        );
    }

    /**
     * Atualizar um corretor externo existente.
     */
    public function update(UpdateCorretorExternoRequest $request, string $id): JsonResponse
    {
        $corretor = $this->repository->findById($id);
        $corretor = $this->repository->update($corretor, $request->validated());

        return ApiResponseService::success(
            new CorretorExternoResource($corretor),
            'Corretor externo atualizado com sucesso.'
        );
    }

    /**
     * Excluir um corretor externo.
     */
    public function destroy(DestroyCorretorExternoRequest $request, string $id): JsonResponse
    {
        $corretor = $this->repository->findById($id);
        $this->repository->delete($corretor);

        return ApiResponseService::noContent();
    }

    /**
     * Listar corretores externos para seleção.
     */
    public function corretoresForSelect(): JsonResponse
    {
        $cacheKey = $this->cache->key('corretores_externos', 'select');

        $corretores = $this->cache->remember(
            'corretores_externos',
            $cacheKey,
            now()->addHours(1),
            fn () => $this->repository->listForSelect(),
        );

        return response()->json(['data' => $corretores]);
    }
}
