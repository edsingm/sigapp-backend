<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

trait LogsAudit
{
    /**
     * Registra uma entrada no audit log.
     *
     * Uso:
     *   $this->audit('user.created', 'Usuário João criado.', ['user_id' => 1]);
     *   $this->audit('tenant.activated', 'Tenant ativado.'); // sem metadata
     *
     * Em Jobs (sem request), user_id / ip / user_agent são opcionais.
     */
    protected function audit(string $action, string $description, array $metadata = []): void
    {
        try {
            $request = request();

            AuditLog::create([
                'user_id' => $request?->user()?->id,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[LogsAudit] Falha ao registrar audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
