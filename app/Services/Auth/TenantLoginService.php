<?php

namespace App\Services\Auth;

use App\Models\User as CentralUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TenantLoginService
{
    /**
     * Tenta o login do usuário no tenant e retorna o payload do token em caso de sucesso.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, user?: \App\Models\Tenant\User, token?: string, abilities?: list<string>, expires_at?: string|null}
     */
    public function attempt(array $credentials, Request $request): array
    {
        $user = \App\Models\Tenant\User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return ['success' => false];
        }

        if ($request->has('device_name')) {
            $user->tokens()->where('name', $credentials['device_name'])->delete();
        }

        $tokenResult = $user->createToken(
            $credentials['device_name'] ?? 'tenant-api-token',
            ['tenant-api'],
            now()->addDays(7)
        );

        return [
            'success' => true,
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
            'abilities' => ['tenant-api'],
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve o identificador do tenant a partir do corpo da requisição ou do cabeçalho X-Tenant.
     * Funciona apenas em ambientes local/testing.
     */
    public function resolveLocalTenantIdentifier(Request $request): ?string
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        $fromBody = $request->input('tenant_identifier');
        if (is_string($fromBody) && trim($fromBody) !== '') {
            return trim($fromBody);
        }

        $fromHeader = $request->header('X-Tenant');
        if (is_string($fromHeader) && trim($fromHeader) !== '') {
            return trim($fromHeader);
        }

        return null;
    }

    /**
     * Calcula a data de expiração do token com base no tipo de usuário e nas habilidades do token.
     *
     * @param  array<int, string>  $abilities
     */
    public function tokenExpiration(mixed $user, array $abilities): \Carbon\Carbon
    {
        $isAdminToken = $user instanceof CentralUser
            && in_array('admin', $abilities, true);

        return $isAdminToken ? now()->addHours(12) : now()->addDays(7);
    }
}
