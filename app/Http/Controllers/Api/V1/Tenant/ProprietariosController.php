<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProprietarioRequest;
use App\Http\Requests\Tenant\UpdateProprietarioRequest;
use App\Http\Resources\Tenant\ProprietarioResource;
use App\Models\Tenant\Proprietario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProprietariosController extends Controller
{
    /**
     * Display a listing of the owners.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Proprietario::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['per_page', 'page', 'terreno_id']);
        
        $cacheKey = "tenant:{$tenantId}:proprietarios:index:" . md5(json_encode($filters));
        
        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:proprietarios"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $perPage = (int) ($request->input('per_page') ?? 10);
            $terrenoId = $request->input('terreno_id');

            $query = Proprietario::with(['terreno', 'createdBy', 'updatedBy'])
                ->orderBy('created_at', 'desc');

            if ($terrenoId) {
                $query->where('terreno_id', $terrenoId);
            }

            $paginator = $query->paginate($perPage);

            return $this->respondWithPagination($paginator, ProprietarioResource::class);
        });
    }

    /**
     * Store a newly created owner in storage.
     */
    public function store(StoreProprietarioRequest $request): JsonResponse
    {
        Gate::authorize('create', Proprietario::class);

        $data = $request->validated();
        $userId = $request->user()->id ?? null;

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $owner = Proprietario::create($data);

        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($owner),
            'message' => 'Proprietário criado com sucesso!'
        ], 201);
    }

    /**
     * Display the specified owner.
     */
    public function show(Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('view', $proprietario);
        $proprietario->load(['terreno', 'createdBy', 'updatedBy']);
        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($proprietario)
        ]);
    }

    /**
     * Update the specified owner in storage.
     */
    public function update(UpdateProprietarioRequest $request, Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('update', $proprietario);
        $data = $request->validated();
        $userId = $request->user()->id ?? null;

        $data['updated_by'] = $userId;

        $proprietario->update($data);

        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($proprietario->fresh(['terreno', 'createdBy', 'updatedBy'])),
            'message' => 'Proprietário atualizado com sucesso!'
        ]);
    }

    /**
     * Remove the specified owner from storage.
     */
    public function destroy(Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('delete', $proprietario);
        $proprietario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proprietário removido com sucesso!'
        ]);
    }
}
