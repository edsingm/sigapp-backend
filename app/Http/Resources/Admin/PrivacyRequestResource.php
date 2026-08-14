<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Central\PrivacyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrivacyRequest */
class PrivacyRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PrivacyRequest $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'protocol' => $item->protocol,
            'kind' => $item->kind->value,
            'subject_type' => $item->subject_type->value,
            'subject_email' => $item->subject_email,
            'tenant_id' => $item->tenant_id,
            'status' => $item->status->value,
            'legal_hold_reason' => $item->legal_hold_reason,
            'received_at' => $item->received_at?->toIso8601String(),
            'due_at' => $item->due_at?->toIso8601String(),
            'assigned_to' => $item->assigned_to,
            'notes' => $item->notes,
            'export_path' => $item->export_path,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}
