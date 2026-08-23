<?php

namespace App\Services\Auth;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TenantUserDirectoryService
{
    /**
     * Normaliza o e-mail para minúsculas e remove espaços.
     */
    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Sincroniza um usuário do tenant com o diretório central.
     */
    public function syncUser(TenantUser $user): void
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        tenancy()->central(function () use ($tenant, $user): void {
            TenantUserDirectory::query()->updateOrCreate(
                [
                    'tenant_id' => (string) $tenant->getKey(),
                    'tenant_user_id' => (string) $user->getKey(),
                ],
                [
                    'email_normalized' => $this->normalizeEmail((string) $user->email),
                    'user_name' => (string) $user->name,
                    'active' => true,
                ]
            );
        });
    }

    /**
     * Remove um usuário do tenant do diretório central.
     */
    public function deleteUser(TenantUser $user): void
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        tenancy()->central(function () use ($tenant, $user): void {
            TenantUserDirectory::query()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('tenant_user_id', (string) $user->getKey())
                ->delete();
        });
    }

    /**
     * Busca candidatos por e-mail no diretório central.
     *
     * Inclui tenants com status elegível a login (active, suspended, under_review)
     * para permitir regularização de cobrança via broker central.
     *
     * @return Collection<int, TenantUserDirectory>
     */
    public function candidatesForEmail(string $email): Collection
    {
        return TenantUserDirectory::query()
            ->with(['tenant.domains'])
            ->where('email_normalized', $this->normalizeEmail($email))
            ->where('active', true)
            ->whereHas(
                'tenant',
                fn ($query) => $query->whereIn('status', TenantStatus::loginEligibleValues())
            )
            ->orderBy('tenant_id')
            ->get();
    }

    /**
     * Reconstrói o diretório de usuários de todos os tenants.
     */
    public function rebuild(): void
    {
        tenancy()->central(fn () => TenantUserDirectory::query()->delete());

        Tenant::query()->chunkById(50, function ($tenants): void {
            foreach ($tenants as $tenant) {
                try {
                    $tenant->run(function () use ($tenant): void {
                        TenantUser::query()
                            ->select(['id', 'name', 'email'])
                            ->chunkById(500, function ($users) use ($tenant): void {
                                $rows = collect($users)
                                    ->filter(fn ($user) => filled($user->email))
                                    ->map(fn ($user) => [
                                        'tenant_id' => (string) $tenant->getKey(),
                                        'tenant_user_id' => (string) $user->id,
                                        'email_normalized' => $this->normalizeEmail((string) $user->email),
                                        'user_name' => (string) $user->name,
                                        'active' => true,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ])
                                    ->values()
                                    ->all();

                                if ($rows !== []) {
                                    tenancy()->central(fn () => TenantUserDirectory::query()->upsert(
                                        $rows,
                                        ['tenant_id', 'tenant_user_id'],
                                        ['email_normalized', 'user_name', 'active', 'updated_at'],
                                    ));
                                }
                            });
                    });
                } catch (\Throwable $exception) {
                    Log::warning('Tenant user directory rebuild skipped tenant', [
                        'tenant_id' => (string) $tenant->getKey(),
                        'error' => $exception->getMessage(),
                    ]);

                    continue;
                }
            }
        });
    }
}
