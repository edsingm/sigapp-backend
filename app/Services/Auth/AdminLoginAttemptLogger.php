<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AdminLoginAttempt;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminLoginAttemptLogger
{
    /**
     * @param  array{
     *     email: string,
     *     successful: bool,
     *     stage?: string|null,
     *     user_id?: int|null,
     *     failure_reason?: string|null,
     *     ip_address?: string|null,
     *     user_agent?: string|null,
     *     request_id?: string|null
     * }  $payload
     */
    public function record(array $payload): void
    {
        try {
            AdminLoginAttempt::query()->create([
                'email' => mb_strtolower(trim($payload['email'])),
                'successful' => (bool) $payload['successful'],
                'stage' => $payload['stage'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'failure_reason' => $payload['failure_reason'] ?? null,
                'ip_address' => $payload['ip_address'] ?? null,
                'user_agent' => $payload['user_agent'] ?? null,
                'request_id' => $payload['request_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::warning('[AdminLoginAttemptLogger] Falha ao registrar tentativa de login', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function countFailedSince(\DateTimeInterface $since): int
    {
        return (int) AdminLoginAttempt::query()
            ->where('successful', false)
            ->where('created_at', '>=', $since)
            ->count();
    }
}
