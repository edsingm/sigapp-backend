<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MarkProjetoReadyRequest;
use App\Http\Requests\Tenant\StoreProjetoRequest;
use App\Http\Requests\Tenant\UpdateProjetoRequest;
use App\Http\Resources\Tenant\ProjetoResource;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Tenant\MobilePushService;
use App\Services\Tenant\ProjetoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ProjetoController extends Controller
{
    public function __construct(
        protected ProjetoService $service,
        protected MobilePushService $mobilePushService
    ) {}

    /**
     * Listar projetos.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Projeto::class);

        try {
            $tenantId = tenant('id') ?? 'central';
            $filters = $request->only(['search', 'status', 'per_page', 'page']);
            $cacheKey = "tenant:{$tenantId}:projetos:index:".md5(json_encode($filters));

            $result = Cache::tags(["tenant:{$tenantId}:projetos"])
                ->remember($cacheKey, now()->addMinutes(15), function () use ($filters) {
                    return $this->service->listar($filters);
                });

            $result->through(fn (Projeto $projeto) => [
                ...((new ProjetoResource($projeto))->resolve()),
                'workflow' => $this->service->workflow($projeto, request()->user()),
            ]);

            return ApiResponseService::paginated($result, 'Projetos carregados com sucesso');
        } catch (\Throwable $e) {
            Log::error('Erro ao listar projetos', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao listar projetos');
        }
    }

    /**
     * Listar terrenos elegíveis para novos projetos.
     */
    public function eligibleTerrenos(Request $request)
    {
        Gate::authorize('viewAny', Terreno::class);

        try {
            $filters = $request->only(['search', 'per_page', 'page']);
            $result = $this->service->listarTerrenosElegiveis($filters);

            $result->through(
                fn ($terreno) => (new TerrenoResource($terreno))->resolve()
            );

            return ApiResponseService::paginated($result, 'Terrenos elegíveis carregados com sucesso');
        } catch (\Throwable $e) {
            Log::error('Erro ao listar terrenos elegíveis para projetos', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao listar terrenos elegíveis');
        }
    }

    /**
     * Criar um novo projeto.
     */
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

    /**
     * Exibir os detalhes de um projeto específico.
     */
    public function show(string $id, Request $request)
    {
        try {
            $projeto = $this->service->buscar((int) $id);
            Gate::authorize('view', $projeto);

            return ApiResponseService::success(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto carregado com sucesso'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\Throwable $e) {
            Log::error('Erro ao carregar projeto', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao carregar projeto');
        }
    }

    /**
     * Atualizar um projeto existente.
     */
    public function update(UpdateProjetoRequest $request, string $id)
    {
        try {
            $projeto = $this->service->buscar((int) $id);
            Gate::authorize('update', $projeto);

            $projeto = $this->service->atualizar($projeto, $request->validated());
            $this->flushProjetoCaches();

            return ApiResponseService::success(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto atualizado com sucesso'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar projeto', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao atualizar projeto');
        }
    }

    /**
     * Marcar um projeto como pronto para registro.
     */
    public function markReady(MarkProjetoReadyRequest $request, string $id)
    {
        try {
            $projeto = $this->service->buscar((int) $id);
            Gate::authorize('markReady', $projeto);

            $projeto = $this->service->marcarProntoParaRegistro($projeto);
            $this->flushProjetoCaches();
            $this->mobilePushService->notifyAllUsers([
                'title' => 'Projeto finalizado',
                'body' => "O projeto {$projeto->nome} foi finalizado após a legalização.",
                'type' => 'projeto.finalizado',
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
                'Projeto finalizado com sucesso'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\RuntimeException $e) {
            return ApiResponseService::error('MARK_READY_ERROR', $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Erro ao finalizar projeto', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao finalizar o projeto');
        }
    }

    /**
     * Cancelar um projeto.
     */
    public function cancel(string $id, Request $request)
    {
        try {
            $projeto = $this->service->buscar((int) $id);
            Gate::authorize('cancel', $projeto);

            $projeto = $this->service->cancelar($projeto);
            $this->flushProjetoCaches();

            return ApiResponseService::success(
                $this->service->workspacePayload($projeto, $request->user()),
                'Projeto cancelado com sucesso'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound('Projeto não encontrado');
        } catch (\Throwable $e) {
            Log::error('Erro ao cancelar projeto', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::serverError('Erro ao cancelar projeto');
        }
    }

    /**
     * Limpar os caches relacionados a projetos.
     */
    protected function flushProjetoCaches(): void
    {
        $tenantId = tenant('id') ?? 'central';

        Cache::tags([
            "tenant:{$tenantId}:projetos",
            "tenant:{$tenantId}:terrenos",
            "tenant:{$tenantId}:viabilidades",
            "tenant:{$tenantId}:legalizacoes",
        ])->flush();
    }
}
