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
     * @return array{consent_log: ConsentLog, is_first_for_consent: bool}
     */
    public function register(array $data, ?string $ip, ?string $userAgent): array
    {
        $isFirstForConsent = ! $this->repository->existsByConsentId($data['consent_id']);

        $consentLog = $this->repository->create([
            'consent_id' => $data['consent_id'],
            'categories' => $data['categories'],
            'version' => $data['version'],
            'ip_hash' => hash('sha256', $ip ?? ''),
            'user_agent' => substr($userAgent ?? '', 0, 500),
            'consented_at' => $data['timestamp'],
        ]);

        return [
            'consent_log' => $consentLog,
            'is_first_for_consent' => $isFirstForConsent,
        ];
    }
}
