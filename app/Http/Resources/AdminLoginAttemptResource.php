<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AdminLoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdminLoginAttempt */
class AdminLoginAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'successful' => (bool) $this->resource->successful,
            'stage' => $this->resource->stage,
            'failure_reason' => $this->resource->failure_reason,
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
            'request_id' => $this->resource->request_id,
            'user' => $user !== null ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
