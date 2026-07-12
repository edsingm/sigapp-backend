<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReportTemplate */
class ReportTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReportTemplate $template */
        $template = $this->resource;

        return [
            'id' => $template->id,
            'name' => $template->name,
            'scope' => $template->scope,
            'definition' => $template->definition,
            'version' => $template->version,
            'is_system' => $template->is_system,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $template->owner?->id,
                'name' => $template->owner?->name,
            ]),
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
