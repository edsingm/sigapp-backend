<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Central\Cidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CidadesController extends Controller
{
    /**
     * Retorna lista de estados únicos (JSON).
     */
    public function index(): JsonResponse
    {
        $states = Cache::rememberForever('central:states', function () {
            return Cidade::states()->get();
        });

        return response()->json([
            'success' => true,
            'data' => $states
        ]);
    }

    /**
     * Retorna lista de cidades de um estado específico (JSON).
     */
    public function getCities(string $stateCode): JsonResponse
    {
        $cacheKey = "central:cities:{$stateCode}";
        
        $cities = Cache::rememberForever($cacheKey, function () use ($stateCode) {
            return Cidade::citiesByState($stateCode)->get(['code', 'city as name']);
        });

        return response()->json([
            'success' => true,
            'data' => $cities
        ]);
    }

    /**
     * Busca cidades pelo nome ou termo parcial.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termo = $request->query('termo');
        if (!$termo) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Termo de busca não informado.'
            ], 400);
        }

        $cidades = Cidade::where('city', 'like', "%{$termo}%")
            ->orderBy('city')
            ->get(['code', 'city', 'state', 'state_code']);

        return response()->json([
            'status' => 'OK',
            'data' => $cidades
        ]);
    }

    /**
     * Retorna dados detalhados de uma cidade pelo código.
     */
    public function dados(Request $request): JsonResponse
    {
        $cityCode = $request->query('cityCode');
        if (!$cityCode) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Código da cidade não informado.'
            ], 400);
        }

        $cidade = Cidade::where('code', $cityCode)->first();
        if (!$cidade) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Cidade não encontrada.'
            ], 404);
        }

        return response()->json([
            'status' => 'OK',
            'data' => $cidade
        ]);
    }
}
