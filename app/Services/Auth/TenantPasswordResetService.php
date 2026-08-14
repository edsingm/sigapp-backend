<?php

namespace App\Services\Auth;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Notifications\TenantUserInviteNotification;
use App\Services\Privacy\LegalDocumentService;
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
        private readonly LegalDocumentService $legalDocuments,
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
     * Envia e-mail de convite (template próprio) com link para definir a senha.
     * Reutiliza o token do broker de senha, mas NÃO usa a notificação de reset.
     */
    public function sendInviteLinkForCurrentTenant(User $user): string
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return Password::INVALID_USER;
        }

        $tenantName = (string) (
            $tenant->getAttribute('name')
            ?: $tenant->getAttribute('slug')
            ?: 'SIG.APP'
        );

        return Password::broker('tenant_users')->sendResetLink(
            ['email' => (string) $user->email],
            function (User $notifiable, string $token) use ($tenant, $tenantName): void {
                $inviteUrl = $this->buildInviteUrl(
                    $tenant,
                    $token,
                    (string) $notifiable->email,
                );

                $notifiable->notify(new TenantUserInviteNotification(
                    inviteUrl: $inviteUrl,
                    expireMinutes: (int) config('auth.passwords.tenant_users.expire', 60),
                    userName: (string) $notifiable->name,
                    tenantName: $tenantName,
                ));
            },
        );
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
    public function resetForCurrentTenant(
        string $email,
        string $token,
        string $password,
        bool $recordLegalAcceptances = false,
    ): string {
        return Password::broker('tenant_users')->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user, string $password) use ($recordLegalAcceptances): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                if ($recordLegalAcceptances) {
                    $this->legalDocuments->recordTenantUserAcceptances((int) $user->getKey());
                }

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
            'tenant' => (string) $tenant->getAttribute('slug'),
        ]);
    }

    /**
     * URL do convite: mesmo endpoint de definir senha, com intent=invite para a UI.
     */
    public function buildInviteUrl(Tenant $tenant, string $token, string $email): string
    {
        return $this->tenantAppUrl->resetPasswordUrl($tenant, [
            'token' => $token,
            'email' => $email,
            'tenant' => (string) $tenant->getAttribute('slug'),
            'intent' => 'invite',
        ]);
    }
}
