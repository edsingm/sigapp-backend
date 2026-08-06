<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AdminMfaAuditRepository
{
    /** @param array<string, mixed> $metadata */
    public function record(?int $userId, string $action, array $metadata = []): AuditLog
    {
        $request = app()->bound('request') ? app('request') : null;

        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $action,
            'metadata' => $metadata !== [] ? $metadata : null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
        ]);
    }
}
