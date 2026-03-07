<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant\TerrenoStatus;

use App\Http\Resources\Tenant\TerrenoStatusResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;


class TerrenoStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TerrenoStatus::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['per_page', 'page', 'search']);
        $cacheKey = "tenant:{$tenantId}:terreno_status:index:" . md5(json_encode($filters));

        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terreno_status"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $perPage = $request->integer('per_page', 10);
            $query = TerrenoStatus::query();

            if ($request->has('search') && $request->search) {
                $query->buscarPorNome($request->search);
            }

            $query->orderBy('nome', 'asc');
            $paginator = $query->paginate($perPage);

            return $this->respondWithPagination($paginator, TerrenoStatusResource::class);
        });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', TerrenoStatus::class);

        $validated = $request->validate([
            'nome' => 'required|string|max:255|unique:terreno_status,nome',
            'cor' => 'required|string|max:20',
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
        ]);

        $status = TerrenoStatus::create($validated);

        return response()->json([
            'success' => true,
            'data' => new TerrenoStatusResource($status),
            'message' => 'Status de terreno criado com sucesso!'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $status = TerrenoStatus::findOrFail($id);
        Gate::authorize('view', $status);
        return response()->json([
            'success' => true,
            'data' => new TerrenoStatusResource($status)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $status = TerrenoStatus::findOrFail($id);
        Gate::authorize('update', $status);

        $validated = $request->validate([
            'nome' => 'sometimes|required|string|max:255|unique:terreno_status,nome,' . $id,
            'cor' => 'sometimes|required|string|max:20',
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
        ]);

        $status->update($validated);

        return response()->json([
            'success' => true,
            'data' => new TerrenoStatusResource($status->fresh()),
            'message' => 'Status de terreno atualizado com sucesso!'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $status = TerrenoStatus::findOrFail($id);
        Gate::authorize('delete', $status);
        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Status de terreno removido com sucesso!'
        ]);
    }

    public function statusForSelect(): JsonResponse
    {
        Gate::authorize('viewAny', TerrenoStatus::class);

        $status = TerrenoStatus::ativos()
            ->select('id', 'nome', 'cor', 'descricao')
            ->orderBy('nome')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }
}
