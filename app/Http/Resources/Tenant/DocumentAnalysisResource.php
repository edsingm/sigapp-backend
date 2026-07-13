<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\DocumentAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentAnalysis */
class DocumentAnalysisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documento_id' => $this->documento_id,
            'status' => $this->status,
            'provider' => $this->provider,
            'model' => $this->model,
            'confidence' => $this->confidence,
            'extracted_fields' => $this->extracted_fields,
            'limitations' => $this->limitations,
            'error_message' => $this->status === 'failed' ? $this->error_message : null,
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
