<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\CentralUserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AdminAuthService
{
    public function __construct(
        private readonly CentralUserRepositoryInterface $userRepository,
    ) {}

    /**
     * Valida as credenciais de um administrador central e emite um token Sanctum.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{user: User, token: string, expires_at: ?string}|null Null quando as credenciais são inválidas.
     */
    public function attempt(array $credentials): ?array
    {
        $user = $this->userRepository->findByEmail((string) $credentials['email']);

        if (
            ! $user
            || ! Hash::check((string) $credentials['password'], (string) $user->getAttribute('password'))
            || ! (bool) $user->getAttribute('is_admin')
        ) {
            return null;
        }

        $deviceName = $credentials['device_name'] ?? 'admin-token';

        if (isset($credentials['device_name'])) {
            $user->tokens()->where('name', $credentials['device_name'])->delete();
        }

        $tokenResult = $user->createToken($deviceName, ['admin'], now()->addHours(12));
        $expiresAt = $tokenResult->accessToken->getAttribute('expires_at');

        return [
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $expiresAt instanceof \DateTimeInterface ? $expiresAt->format(\DateTimeInterface::ATOM) : null,
        ];
    }
}
