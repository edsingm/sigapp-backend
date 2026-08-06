<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\Contracts\CentralUserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AdminAuthService
{
    public function __construct(
        private readonly CentralUserRepositoryInterface $userRepository,
        private readonly AdminMfaService $mfaService,
    ) {}

    /**
     * Valida as credenciais de um administrador central e inicia o segundo fator.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>|null Null quando as credenciais são inválidas.
     */
    public function attempt(array $credentials, ?string $ipAddress, ?string $userAgent): ?array
    {
        $user = $this->userRepository->findByEmail((string) $credentials['email']);

        if (
            ! $user
            || ! Hash::check((string) $credentials['password'], (string) $user->getAttribute('password'))
            || ! (bool) $user->getAttribute('is_admin')
        ) {
            return null;
        }

        return $this->mfaService->beginLogin(
            $user,
            (string) ($credentials['device_name'] ?? 'admin-token'),
            $ipAddress,
            $userAgent,
        );
    }
}
