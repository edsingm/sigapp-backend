<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\DocumentRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentRequirement */
class DocumentRequirementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DocumentRequirement $requirement */
        $requirement = $this->resource;

        return [
            'id' => $requirement->id,
            'entity_type' => $requirement->entity_type,
            'entity_id' => $requirement->entity_id,
            'phase' => $requirement->phase,
            'document_type' => $requirement->document_type,
            'label' => $requirement->label,
            'required' => $requirement->required,
            'active' => $requirement->active,
        ];
    }
}
