<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\DocumentReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentReview */
class DocumentReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documento_id' => $this->documento_id,
            'reviewer_id' => $this->reviewer_id,
            'status' => $this->status,
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'notes' => $this->notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
