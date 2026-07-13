<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Terreno $terreno */
        $terreno = $this->resource;
        $status = $terreno->workflow_status_code ?: 'em_analise';
        $meta = LandWorkflowService::statuses()[$status] ?? null;
        $enteredAt = $terreno->workflow_status_changed_at ?? $terreno->created_at;

        return [
            'id' => $terreno->id,
            'title' => $terreno->nome,
            'status' => [
                'code' => $status,
                'label' => $meta['label'] ?? $status,
                'phase' => $meta['stage'] ?? null,
            ],
            'responsible' => $this->whenLoaded('responsavel', fn () => [
                'id' => $terreno->responsavel?->id,
                'name' => $terreno->responsavel?->name,
            ]),
            'aging_days' => $enteredAt?->diffInDays(now()) ?? 0,
            'vgv' => $terreno->getAttribute('vgv_total') !== null
                ? (float) $terreno->getAttribute('vgv_total')
                : null,
            'units' => $terreno->getAttribute('total_unidades') !== null
                ? (int) $terreno->getAttribute('total_unidades')
                : null,
            'alerts' => [],
            'href' => "/sig/terrenos/{$terreno->id}",
        ];
    }
}
