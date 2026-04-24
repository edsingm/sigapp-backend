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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CorretoresExternosController extends Controller
{
    public function __construct(
        protected CorretorExternoRepository $repository
    ) {}

    /**
     * Listar os corretores externos.
     */
    public function index(): JsonResponse
    {
        $tenantId = tenant('id') ?? 'central';
        $perPage = request()->integer('per_page', 10);
        $filters = request()->only(['search']);
        $cacheKey = "tenant:{$tenantId}:corretores_externos:index:".md5(json_encode($filters).":{$perPage}");

        $paginator = Cache::tags(["tenant:{$tenantId}:corretores_externos"])
            ->remember($cacheKey, now()->addMinutes(30), fn () =>
                $this->repository->paginate($perPage, $filters)
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
        $tenantId = tenant('id') ?? 'central';
        $cacheKey = "tenant:{$tenantId}:corretores_externos:select";

        $corretores = Cache::tags(["tenant:{$tenantId}:corretores_externos"])
            ->remember($cacheKey, now()->addHours(1), fn () =>
                $this->repository->listForSelect()
            );

        return response()->json(['data' => $corretores]);
    }
}
