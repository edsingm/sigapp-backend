<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConsentLog;
use App\Repositories\ConsentLogRepository;

class ConsentLogService
{
    public function __construct(
        private readonly ConsentLogRepository $repository,
    ) {}

    /**
     * Registra o consentimento de cookies (LGPD).
     *
     * O IP é armazenado apenas como hash SHA-256 para anonimização (LGPD Art. 12).
     *
     * @param  array<string, mixed>  $data  Dados validados (consent_id, categories, version, timestamp).
     */
    public function register(array $data, ?string $ip, ?string $userAgent): ConsentLog
    {
        return $this->repository->updateOrCreateByConsentId($data['consent_id'], [
            'categories' => $data['categories'],
            'version' => $data['version'],
            'ip_hash' => hash('sha256', $ip ?? ''),
            'user_agent' => substr($userAgent ?? '', 0, 500),
            'consented_at' => $data['timestamp'],
        ]);
    }
}
