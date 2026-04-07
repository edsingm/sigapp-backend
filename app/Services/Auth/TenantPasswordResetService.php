<?php

namespace App\Services\Auth;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Support\TenantAppUrl;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class TenantPasswordResetService
{
    public function __construct(
        private readonly TenantAppUrl $tenantAppUrl,
        private readonly TenantUserDirectoryService $directoryService,
    ) {}

    /**
     * Envia o link de redefinição de senha para o tenant atual.
     */
    public function sendResetLinkForCurrentTenant(string $email): string
    {
        return Password::broker('tenant_users')->sendResetLink([
            'email' => $email,
        ]);
    }

    /**
     * Envia o link de redefinição de senha para todos os tenants ativos onde o e-mail existe.
     */
    public function sendResetLinkAcrossActiveTenants(string $email): int
    {
        $sent = 0;
        $tenants = $this->directoryService->candidatesForEmail($email)
            ->pluck('tenant')
            ->filter(fn ($tenant) => $tenant instanceof Tenant)
            ->unique(fn (Tenant $tenant) => (string) $tenant->getKey())
            ->values();

        foreach ($tenants as $tenant) {
            try {
                $status = $tenant->run(function () use ($email) {
                    $userExists = User::query()
                        ->where('email', $email)
                        ->exists();

                    if (! $userExists) {
                        return null;
                    }

                    return Password::broker('tenant_users')->sendResetLink([
                        'email' => $email,
                    ]);
                });
            } catch (\Throwable $exception) {
                Log::warning('Failed to send tenant password reset link', [
                    'tenant_id' => (string) $tenant->id,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($status === Password::RESET_LINK_SENT) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Realiza a redefinição de senha para o tenant atual.
     */
    public function resetForCurrentTenant(string $email, string $token, string $password): string
    {
        return Password::broker('tenant_users')->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );
    }

    /**
     * Constrói a URL de redefinição de senha para um tenant específico.
     */
    public function buildResetUrl(Tenant $tenant, string $token, string $email): string
    {
        return $this->tenantAppUrl->resetPasswordUrl($tenant, [
            'token' => $token,
            'email' => $email,
            'tenant' => (string) $tenant->slug,
        ]);
    }
}
