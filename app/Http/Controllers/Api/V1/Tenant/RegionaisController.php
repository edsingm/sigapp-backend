<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Regional;
use App\Http\Resources\Tenant\RegionalResource;
use App\Http\Requests\Tenant\StoreRegionalRequest;
use App\Http\Requests\Tenant\UpdateRegionalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RegionaisController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Regional::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['per_page', 'page', 'q']);
        $cacheKey = "tenant:{$tenantId}:regionais:index:" . md5(json_encode($filters));

        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:regionais"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $perPage = $request->integer('per_page', 10);
            $query = Regional::query()->with(['responsavel', 'createdBy', 'updatedBy']);

            if ($request->has('q') && $request->q) {
                $search = $request->q;
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                        ->orWhere('cidade', 'like', "%{$search}%")
                        ->orWhere('estado', 'like', "%{$search}%");
                });
            }

            $query->orderBy('nome', 'asc');
            $paginator = $query->paginate($perPage);

            return $this->respondWithPagination($paginator, RegionalResource::class);
        });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRegionalRequest $request): JsonResponse
    {
        Gate::authorize('create', Regional::class);

        $data = $request->validated();
        $userId = $request->user()->id ?? null;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $regional = Regional::create($data);

        return response()->json([
            'success' => true,
            'data' => new RegionalResource($regional->load(['responsavel', 'createdBy', 'updatedBy'])),
            'message' => 'Regional criada com sucesso!'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $regional = Regional::with(['responsavel', 'createdBy', 'updatedBy'])->findOrFail($id);
        Gate::authorize('view', $regional);

        return response()->json([
            'success' => true,
            'data' => new RegionalResource($regional)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRegionalRequest $request, string $id): JsonResponse
    {
        $regional = Regional::findOrFail($id);
        Gate::authorize('update', $regional);
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id ?? null;

        $regional->update($data);

        return response()->json([
            'success' => true,
            'data' => new RegionalResource($regional->load(['responsavel', 'createdBy', 'updatedBy'])),
            'message' => 'Regional atualizada com sucesso!'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $regional = Regional::findOrFail($id);
        Gate::authorize('delete', $regional);
        $regional->delete();

        return response()->json([
            'success' => true,
            'message' => 'Regional excluída com sucesso!'
        ]);
    }

    /**
     * Retorna lista de regionais para select.
     */
    public function regionaisForSelect(): JsonResponse
    {
        Gate::authorize('viewAny', Regional::class);

        $regionais = Regional::select('id', 'nome')
            ->orderBy('nome')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regionais
        ]);
    }
}
