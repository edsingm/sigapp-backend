<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePremissasViabilidadeRequest;
use App\Http\Requests\Tenant\UpdatePremissasViabilidadeRequest;
use App\Http\Resources\Tenant\PremissasViabilidadeResource;
use App\Models\Tenant\PremissasViabilidade;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class PremissasViabilidadeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $perfil = $request->string('perfil_financiamento')->toString();

        $premissas = PremissasViabilidade::query()
            ->when($perfil !== '', fn ($q) => $q->porPerfil($perfil))
            ->orderBy('perfil_financiamento')
            ->orderBy('versao', 'desc')
            ->paginate($perPage);

        return PremissasViabilidadeResource::collection($premissas)
            ->additional([
                'message' => 'Premissas recuperadas com sucesso',
                'current_page' => $premissas->currentPage(),
                'last_page' => $premissas->lastPage(),
                'total' => $premissas->total(),
                'per_page' => $premissas->perPage(),
            ]);
    }

    public function show(int $id)
    {
        $premissas = PremissasViabilidade::find($id);

        if (! $premissas) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        return ApiResponseService::success(
            new PremissasViabilidadeResource($premissas),
            'Premissas recuperadas com sucesso'
        );
    }

    public function store(StorePremissasViabilidadeRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $premissas = PremissasViabilidade::create($validated);

        return ApiResponseService::created(
            new PremissasViabilidadeResource($premissas),
            'Premissas criadas com sucesso'
        );
    }

    public function update(UpdatePremissasViabilidadeRequest $request, int $id)
    {
        $premissas = PremissasViabilidade::find($id);

        if (! $premissas) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        $premissas->update($validated);

        return ApiResponseService::success(
            new PremissasViabilidadeResource($premissas),
            'Premissas atualizadas com sucesso'
        );
    }

    public function destroy(int $id)
    {
        $premissas = PremissasViabilidade::find($id);

        if (! $premissas) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        $premissas->delete();

        return ApiResponseService::success(null, 'Premissas excluídas com sucesso');
    }
}
