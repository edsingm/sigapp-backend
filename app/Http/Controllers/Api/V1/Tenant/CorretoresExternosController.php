<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant\CorretorExterno;
use App\Http\Resources\Tenant\CorretorExternoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CorretoresExternosController extends Controller
{
    /**
     * Listar os corretores externos.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CorretorExterno::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['per_page', 'page', 'search']);
        $cacheKey = "tenant:{$tenantId}:corretores_externos:index:" . md5(json_encode($filters));

        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:corretores_externos"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $perPage = $request->integer('per_page', 10);

            $query = CorretorExterno::query();

            if ($request->has('search') && $request->search) {
                $query->search($request->search);
            }

            $query->orderBy('nome', 'asc');

            $paginator = $query->paginate($perPage);

            return $this->respondWithPagination($paginator, CorretorExternoResource::class);
        });
    }

    /**
     * Armazenar um novo corretor externo.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', CorretorExterno::class);

        $validated = $request->validate(CorretorExterno::rules());

        $corretorExterno = CorretorExterno::create($validated);

        return response()->json([
            'data' => new CorretorExternoResource($corretorExterno),
            'message' => 'Corretor externo criado com sucesso.',
        ], 201);
    }

    /**
     * Exibir os detalhes de um corretor externo específico.
     */
    public function show(string $id): JsonResponse
    {
        $corretor = CorretorExterno::findOrFail($id);
        Gate::authorize('view', $corretor);
        return response()->json(['data' => new CorretorExternoResource($corretor)]);
    }

    /**
     * Atualizar um corretor externo existente.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $corretor = CorretorExterno::findOrFail($id);
        Gate::authorize('update', $corretor);

        $validated = $request->validate(CorretorExterno::rules($id));

        $corretor->update($validated);

        return response()->json([
            'data' => new CorretorExternoResource($corretor),
            'message' => 'Corretor externo atualizado com sucesso.',
        ]);
    }

    /**
     * Excluir um corretor externo.
     */
    public function destroy(string $id): JsonResponse
    {
        $corretor = CorretorExterno::findOrFail($id);
        Gate::authorize('delete', $corretor);

        $corretor->delete();

        return response()->json([
            'message' => 'Corretor externo excluído com sucesso.',
        ]);
    }

    /**
     * Listar corretores externos para seleção.
     */
    public function corretoresForSelect()
    {
        Gate::authorize('viewAny', CorretorExterno::class);

        $tenantId = tenant('id') ?? 'central';
        $cacheKey = "tenant:{$tenantId}:corretores_externos:select";

        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:corretores_externos"])->remember($cacheKey, now()->addHours(1), function () {
            $corretores = CorretorExterno::orderBy('nome', 'asc')->get(['id', 'nome']);
            return response()->json(['data' => $corretores]);
        });
    }
}
