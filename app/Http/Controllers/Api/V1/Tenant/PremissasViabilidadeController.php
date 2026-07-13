<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Exceptions\PremissasViabilidadeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyPremissasViabilidadeRequest;
use App\Http\Requests\Tenant\StorePremissasViabilidadeRequest;
use App\Http\Requests\Tenant\UpdatePremissasViabilidadeRequest;
use App\Http\Resources\Tenant\PremissasViabilidadeResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\PremissasViabilidadeCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PremissasViabilidadeController extends Controller
{
    public function __construct(
        private readonly PremissasViabilidadeCrudService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 10);
        $perfil = $request->string('perfil_financiamento')->toString() ?: null;
        $ativo = $request->has('ativo') ? $request->boolean('ativo') : null;

        $premissas = $this->service->list($perfil, $perPage, $ativo);

        return PremissasViabilidadeResource::collection($premissas);
    }

    public function show(int $id): JsonResponse
    {
        $premissa = $this->service->findById($id);

        if (! $premissa) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        return ApiResponseService::success(
            new PremissasViabilidadeResource($premissa),
            'Premissas recuperadas com sucesso'
        );
    }

    public function history(int $id): JsonResponse
    {
        $premissa = $this->service->findById($id);

        if (! $premissa) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        $history = $this->service->history($premissa);

        return ApiResponseService::success([
            'current' => new PremissasViabilidadeResource($history['current']),
            'previous' => $history['previous']
                ? new PremissasViabilidadeResource($history['previous'])
                : null,
            'versions' => PremissasViabilidadeResource::collection($history['versions'])->resolve(),
        ], 'Histórico de premissas recuperado com sucesso');
    }

    public function store(StorePremissasViabilidadeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        try {
            $premissa = $this->service->create($validated);
        } catch (PremissasViabilidadeException $e) {
            throw $e;
        }

        return ApiResponseService::created(
            new PremissasViabilidadeResource($premissa),
            'Premissas criadas com sucesso'
        );
    }

    public function update(UpdatePremissasViabilidadeRequest $request, int $id): JsonResponse
    {
        $premissa = $this->service->findById($id);

        if (! $premissa) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        try {
            $premissa = $this->service->update($premissa, $validated);
        } catch (PremissasViabilidadeException $e) {
            throw $e;
        }

        return ApiResponseService::success(
            new PremissasViabilidadeResource($premissa),
            'Premissas atualizadas com sucesso'
        );
    }

    public function destroy(DestroyPremissasViabilidadeRequest $request, int $id): JsonResponse
    {
        $premissa = $this->service->findById($id);

        if (! $premissa) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        try {
            $result = $this->service->delete($premissa);
        } catch (PremissasViabilidadeException $e) {
            throw $e;
        }

        if (($result['action'] ?? null) === 'deactivated') {
            return ApiResponseService::success(
                new PremissasViabilidadeResource($result['premissa']),
                'Premissas referenciadas por estudos foram inativadas (não excluídas).'
            );
        }

        return ApiResponseService::success(null, 'Premissas excluídas com sucesso');
    }
}
