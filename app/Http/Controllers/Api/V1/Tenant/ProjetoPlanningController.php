<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ProjetoDependencyRequest;
use App\Http\Requests\Tenant\ProjetoMilestoneRequest;
use App\Http\Requests\Tenant\ProjetoRiskRequest;
use App\Http\Requests\Tenant\ReorderProjetoMilestonesRequest;
use App\Http\Resources\Tenant\ProjetoDependencyResource;
use App\Http\Resources\Tenant\ProjetoMilestoneResource;
use App\Http\Resources\Tenant\ProjetoRiskResource;
use App\Models\Tenant\Projeto;
use App\Services\ApiResponseService;
use App\Services\Tenant\ProjetoPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ProjetoPlanningController extends Controller
{
    public function __construct(
        private readonly ProjetoPlanningService $service,
    ) {}

    public function milestones(string $projetoId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('view', $projeto);

        return ApiResponseService::success(
            ProjetoMilestoneResource::collection($this->service->milestones($projeto)),
            'Milestones carregados com sucesso',
        );
    }

    public function storeMilestone(ProjetoMilestoneRequest $request, string $projetoId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('update', $projeto);

        return ApiResponseService::created(
            new ProjetoMilestoneResource($this->service->createMilestone($projeto, $request->validated())),
            'Milestone criado com sucesso',
        );
    }

    public function updateMilestone(ProjetoMilestoneRequest $request, string $projetoId, string $milestoneId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        $milestone = $this->service->findMilestone($projeto, (int) $milestoneId);
        Gate::authorize('update', $projeto);

        try {
            return ApiResponseService::success(
                new ProjetoMilestoneResource($this->service->updateMilestone($projeto, $milestone, $request->validated())),
                'Milestone atualizado com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('PROJECT_PLANNING_ERROR', $exception->getMessage(), null, 422);
        }
    }

    public function destroyMilestone(string $projetoId, string $milestoneId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        $milestone = $this->service->findMilestone($projeto, (int) $milestoneId);
        Gate::authorize('update', $projeto);

        try {
            $this->service->deleteMilestone($projeto, $milestone);

            return ApiResponseService::noContent();
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('PROJECT_PLANNING_ERROR', $exception->getMessage(), null, 422);
        }
    }

    public function reorderMilestones(ReorderProjetoMilestonesRequest $request, string $projetoId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('update', $projeto);

        try {
            $milestones = $this->service->reorderMilestones($projeto, $request->validated('milestone_ids'));

            return ApiResponseService::success(
                ProjetoMilestoneResource::collection($milestones),
                'Milestones reordenados com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('PROJECT_PLANNING_ERROR', $exception->getMessage(), null, 422);
        }
    }

    public function dependencies(string $projetoId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('view', $projeto);

        return ApiResponseService::success(
            ProjetoDependencyResource::collection($this->service->dependencies($projeto)),
            'Dependências carregadas com sucesso',
        );
    }

    public function storeDependency(ProjetoDependencyRequest $request, string $projetoId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('update', $projeto);

        try {
            return ApiResponseService::created(
                new ProjetoDependencyResource($this->service->createDependency($projeto, $request->validated())),
                'Dependência criada com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('PROJECT_DEPENDENCY_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function destroyDependency(string $projetoId, string $dependency): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('update', $projeto);

        $this->service->deleteDependency($projeto, (int) $dependency);

        return ApiResponseService::noContent();
    }

    public function risks(string $projetoId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('view', $projeto);

        return ApiResponseService::success(
            ProjetoRiskResource::collection($this->service->risks($projeto)),
            'Riscos carregados com sucesso',
        );
    }

    public function storeRisk(ProjetoRiskRequest $request, string $projetoId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('update', $projeto);

        return ApiResponseService::created(
            new ProjetoRiskResource($this->service->createRisk($projeto, $request->validated())),
            'Risco criado com sucesso',
        );
    }

    public function updateRisk(ProjetoRiskRequest $request, string $projetoId, string $riskId): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        $risk = $this->service->findRisk($projeto, (int) $riskId);
        Gate::authorize('update', $projeto);

        try {
            return ApiResponseService::success(
                new ProjetoRiskResource($this->service->updateRisk($projeto, $risk, $request->validated())),
                'Risco atualizado com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('PROJECT_PLANNING_ERROR', $exception->getMessage(), null, 422);
        }
    }

    public function destroyRisk(string $projetoId, string $risk): JsonResponse
    {
        $projeto = $this->findProjeto($projetoId);
        Gate::authorize('update', $projeto);

        $this->service->deleteRisk($projeto, (int) $risk);

        return ApiResponseService::noContent();
    }

    private function findProjeto(string $projetoId): Projeto
    {
        return $this->service->findProjeto((int) $projetoId);
    }
}
