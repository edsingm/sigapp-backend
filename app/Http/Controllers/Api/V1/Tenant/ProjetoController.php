<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MarkProjetoReadyRequest;
use App\Http\Requests\Tenant\StoreProjetoRequest;
use App\Http\Requests\Tenant\UpdateProjetoRequest;
use App\Http\Resources\tenant\ProjetoResource;
use App\Http\Resources\tenant\TerrenoResource;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Tenant\MobilePushService;
use App\Services\Tenant\ProjetoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ProjetoController extends Controller
{
    public function __construct(
        protected ProjetoService $service,
        protected MobilePushService $mobilePushService
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Projeto::class);

        try {
            $tenantId = tenant('id') ?? 'central';
            $filters = $request->only(['search', 'status', 'per_page', 'page']);
            $cacheKey = "tenant:{$tenantId}:projetos:index:" . md5(json_encode($filters));

            $result = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:projetos"])
                ->remember($cacheKey, now()->addMinutes(15), function () use ($filters) {
                    return $this->service->listar($filters);
                });

            $result->setCollection(
                $result->getCollection()->map(fn (Projeto $projeto) => [
                    ...((new ProjetoResource($projeto))->resolve()),
                    'workflow' => $this->service->workflow($projeto, request()->user()),
                ])
            );

            return ApiResponseService::paginated($result, 'Projetos carregados com sucesso');
        } catch (\Throwable $e) {
            Log::error('Erro ao listar projetos', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao listar projetos');
        }
    }

    public function eligibleTerrenos(Request $request)
    {
        Gate::authorize('viewAny', Terreno::class);

        try {
            $filters = $request->only(['search', 'per_page', 'page']);
            $result = $this->service->listarTerrenosElegiveis($filters);

            $result->setCollection(
                $result->getCollection()->map(fn ($terreno) => (new TerrenoResource($terreno))->resolve())
            );

            return ApiResponseService::paginated($result, 'Terrenos elegíveis carregados com sucesso');
        } catch (\Throwable $e) {
            Log::error('Erro ao listar terrenos elegíveis para projetos', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao listar terrenos elegíveis');
        }
    }

    public function store(StoreProjetoRequest $request)
    {
        Gate::authorize('create', Projeto::class);

        try {
            $projeto = $this->service->criar($request->validated());
            $this->flushProjetoCaches();

            return ApiResponseService::created(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto criado com sucesso'
            );
        } catch (\RuntimeException $e) {
            return ApiResponseService::error('CREATE_ERROR', $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Erro ao criar projeto', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao criar projeto');
        }
    }

    public function show(string $id, Request $request)
    {
        try {
            $projeto = $this->service->buscar((int) $id);
            Gate::authorize('view', $projeto);

            return ApiResponseService::success(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto carregado com sucesso'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\Throwable $e) {
            Log::error('Erro ao carregar projeto', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao carregar projeto');
        }
    }

    public function update(UpdateProjetoRequest $request, string $id)
    {
        try {
            $projeto = Projeto::findOrFail($id);
            Gate::authorize('update', $projeto);

            $projeto = $this->service->atualizar($projeto, $request->validated());
            $this->flushProjetoCaches();

            return ApiResponseService::success(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto atualizado com sucesso'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar projeto', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao atualizar projeto');
        }
    }

    public function markReady(MarkProjetoReadyRequest $request, string $id)
    {
        try {
            $projeto = Projeto::findOrFail($id);
            Gate::authorize('markReady', $projeto);

            $projeto = $this->service->marcarProntoParaRegistro($projeto);
            $this->flushProjetoCaches();
            $this->mobilePushService->notifyAllUsers([
                'title' => 'Projeto pronto para registro',
                'body' => "O projeto {$projeto->nome} foi marcado como pronto para registro.",
                'type' => 'projeto.pronto_para_registro',
                'entity_type' => 'projeto',
                'entity_id' => (string) $projeto->id,
                'target_route' => "/projetos/{$projeto->id}",
                'payload' => [
                    'tenant_slug' => tenant('slug'),
                    'project_id' => $projeto->id,
                ],
            ], $request->user());

            return ApiResponseService::success(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto marcado como pronto para registro'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\RuntimeException $e) {
            return ApiResponseService::error('MARK_READY_ERROR', $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Erro ao marcar projeto como pronto para registro', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao concluir o projeto');
        }
    }

    public function cancel(string $id, Request $request)
    {
        try {
            $projeto = Projeto::findOrFail($id);
            Gate::authorize('cancel', $projeto);

            $projeto = $this->service->cancelar($projeto);
            $this->flushProjetoCaches();

            return ApiResponseService::success(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto cancelado com sucesso'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\Throwable $e) {
            Log::error('Erro ao cancelar projeto', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao cancelar projeto');
        }
    }

    protected function flushProjetoCaches(): void
    {
        $tenantId = tenant('id') ?? 'central';

        \Illuminate\Support\Facades\Cache::tags([
            "tenant:{$tenantId}:projetos",
            "tenant:{$tenantId}:terrenos",
            "tenant:{$tenantId}:viabilidades",
            "tenant:{$tenantId}:legalizacoes",
        ])->flush();
    }
}
