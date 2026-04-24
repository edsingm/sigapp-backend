<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ShowTerrenoWorkflowRequest;
use App\Http\Requests\Tenant\TransitionTerrenoWorkflowRequest;
use App\Http\Requests\Tenant\UpdateTerrenoQualificationRequest;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Http\Resources\Tenant\TerrenoWorkflowResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\TerrenoWorkflowService;

class TerrenoWorkflowController extends Controller
{
    public function __construct(
        private readonly TerrenoWorkflowService $service
    ) {}

    /**
     * Exibir o status atual e as opções de workflow para um terreno.
     */
    public function show(ShowTerrenoWorkflowRequest $request, string $id)
    {
        return ApiResponseService::success(
            new TerrenoWorkflowResource($this->service->show($id))
        );
    }

    /**
     * Atualizar o status do workflow de um terreno (transição de status).
     */
    public function update(TransitionTerrenoWorkflowRequest $request, string $id)
    {
        $updated = $this->service->transition($id, $request->validated(), $request->user());

        return ApiResponseService::success(
            new TerrenoResource($updated),
            'Workflow do terreno atualizado com sucesso.'
        );
    }

    /**
     * Atualizar os dados de qualificação de um terreno no workflow.
     */
    public function updateQualification(UpdateTerrenoQualificationRequest $request, string $id)
    {
        $updated = $this->service->updateQualification($id, $request->validated(), $request->user());

        return ApiResponseService::success(
            new TerrenoResource($updated),
            'Qualificação atualizada com sucesso.'
        );
    }
}
