<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\MobileCaptureAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MobileCaptureAttachment */
class MobileCaptureAttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var MobileCaptureAttachment $attachment */
        $attachment = $this->resource;

        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'checksum' => $attachment->checksum,
            'status' => $attachment->status,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
