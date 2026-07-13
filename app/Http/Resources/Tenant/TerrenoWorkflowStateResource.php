<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerrenoWorkflowStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this['status'],
            'phase' => $this['phase'],
            'entered_at' => $this['entered_at'],
            'age_days' => $this['age_days'],
            'is_overdue' => $this['is_overdue'],
            'is_terminal' => $this['is_terminal'],
            'primary_action' => $this['primary_action'],
            'allowed_actions' => $this['allowed_actions'],
            'blocked_actions' => $this['blocked_actions'],
            'responsible' => $this['responsible'],
            'generated_at' => $this['generated_at'],
        ];
    }
}
