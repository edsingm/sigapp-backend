<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\MobileCapture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MobileCapture */
class MobileCaptureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var MobileCapture $capture */
        $capture = $this->resource;

        return [
            'client_id' => $capture->client_id,
            'version' => (int) $capture->version,
            'status' => $capture->status,
            'payload' => $capture->payload,
            'location' => [
                'latitude' => $capture->latitude !== null ? (float) $capture->latitude : null,
                'longitude' => $capture->longitude !== null ? (float) $capture->longitude : null,
                'accuracy' => $capture->accuracy !== null ? (float) $capture->accuracy : null,
                'captured_at' => $capture->captured_at?->toIso8601String(),
            ],
            'terreno_id' => $capture->terreno_id,
            'conflict_details' => $capture->conflict_details,
            'committed_at' => $capture->committed_at?->toIso8601String(),
            'attachments' => MobileCaptureAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $capture->created_at?->toIso8601String(),
            'updated_at' => $capture->updated_at?->toIso8601String(),
        ];
    }
}
