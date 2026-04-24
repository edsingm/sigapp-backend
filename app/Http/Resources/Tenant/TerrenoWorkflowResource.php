<?php

namespace App\Http\Resources\Tenant;

use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerrenoWorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workflowService = app(LandWorkflowService::class);
        $transitionOptions = $workflowService->transitionOptions($this->resource);

        return [
            'current_status' => $this->workflow_status_code,
            'current_stage' => $this->workflow_stage,
            'available_transitions' => $transitionOptions['available'],
            'blocked_transitions' => $transitionOptions['blocked'],
            'checklist' => $workflowService->checklist($this->resource),
        ];
    }
}
