<?php

namespace App\Services\Auth;

use App\Models\Central\Tenant;
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
    ) {
    }

    public function sendResetLinkForCurrentTenant(string $email): string
    {
        return Password::broker('tenant_users')->sendResetLink([
            'email' => $email,
        ]);
    }

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
                    $userExists = \App\Models\Tenant\User::query()
                        ->where('email', $email)
                        ->exists();

                    if (!$userExists) {
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

    public function resetForCurrentTenant(string $email, string $token, string $password): string
    {
        return Password::broker('tenant_users')->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (\App\Models\Tenant\User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );
    }

    public function buildResetUrl(Tenant $tenant, string $token, string $email): string
    {
        return $this->tenantAppUrl->resetPasswordUrl($tenant, [
            'token' => $token,
            'email' => $email,
            'tenant' => (string) $tenant->slug,
        ]);
    }
}
