<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Cidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'data' => $states,
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
            'data' => $cities,
        ]);
    }

    /**
     * Busca cidades pelo nome ou termo parcial.
     */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'termo' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $termo = $request->query('termo');

        $cidades = Cidade::whereRaw(
            'unaccent(city) ILIKE unaccent(?)',
            ["%{$termo}%"]
        )
            ->orderBy('city')
            ->limit(100)
            ->get(['code', 'city', 'state', 'state_code']);

        return response()->json([
            'status' => 'OK',
            'data' => $cidades,
        ]);
    }

    /**
     * Retorna dados detalhados de uma cidade pelo código.
     */
    public function dados(Request $request): JsonResponse
    {
        $request->validate([
            'cityCode' => ['required', 'string', 'max:20'],
        ]);

        $cityCode = $request->query('cityCode');

        $cidade = Cidade::where('code', $cityCode)->first();
        if (! $cidade) {
            return response()->json([
                'status' => 'ERROR',
                'message' => language()->t('CITY_NOT_FOUND'),
            ], 404);
        }

        return response()->json([
            'status' => 'OK',
            'data' => $cidade,
        ]);
    }
}
