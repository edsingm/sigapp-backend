<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuscarCidadesRequest;
use App\Http\Requests\DadosCidadeRequest;
use App\Http\Resources\CidadeBuscaResource;
use App\Http\Resources\CidadeDadosResource;
use App\Http\Resources\CidadeOpcaoResource;
use App\Http\Resources\EstadoResource;
use App\Repositories\CidadeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CidadesController extends Controller
{
    public function __construct(
        private readonly CidadeRepository $cidadeRepository,
    ) {}

    /**
     * Retorna lista de estados únicos (JSON).
     */
    public function index(): JsonResponse
    {
        $states = Cache::rememberForever('central:states', function () {
            return $this->cidadeRepository->listStates();
        });

        return response()->json([
            'success' => true,
            'data' => EstadoResource::collection($states),
        ]);
    }

    /**
     * Retorna lista de cidades de um estado específico (JSON).
     */
    public function getCities(string $stateCode): JsonResponse
    {
        $cacheKey = "central:cities:{$stateCode}";

        $cities = Cache::rememberForever($cacheKey, function () use ($stateCode) {
            return $this->cidadeRepository->listByState($stateCode);
        });

        return response()->json([
            'success' => true,
            'data' => CidadeOpcaoResource::collection($cities),
        ]);
    }

    /**
     * Busca cidades pelo nome ou termo parcial.
     */
    public function buscar(BuscarCidadesRequest $request): JsonResponse
    {
        $cidades = $this->cidadeRepository->searchByTerm((string) $request->validated('termo'));

        return response()->json([
            'status' => 'OK',
            'data' => CidadeBuscaResource::collection($cidades),
        ]);
    }

    /**
     * Retorna dados detalhados de uma cidade pelo código.
     */
    public function dados(DadosCidadeRequest $request): JsonResponse
    {
        $cidade = $this->cidadeRepository->findByCode((string) $request->validated('cityCode'));
        if (! $cidade) {
            return response()->json([
                'status' => 'ERROR',
                'message' => language()->t('CITY_NOT_FOUND'),
            ], 404);
        }

        return response()->json([
            'status' => 'OK',
            'data' => new CidadeDadosResource($cidade),
        ]);
    }
}
