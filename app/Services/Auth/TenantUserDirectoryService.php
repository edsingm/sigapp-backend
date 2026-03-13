<?php

namespace App\Services\Auth;

use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TenantUserDirectoryService
{
    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function syncUser(TenantUser $user): void
    {
        $tenant = tenant();

        if (!$tenant instanceof Tenant) {
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

    public function deleteUser(TenantUser $user): void
    {
        $tenant = tenant();

        if (!$tenant instanceof Tenant) {
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
     * @return Collection<int, TenantUserDirectory>
     */
    public function candidatesForEmail(string $email): Collection
    {
        return TenantUserDirectory::query()
            ->with(['tenant.domains'])
            ->where('email_normalized', $this->normalizeEmail($email))
            ->where('active', true)
            ->whereHas('tenant', fn ($query) => $query->where('status', Tenant::STATUS_ACTIVE))
            ->orderBy('tenant_id')
            ->get();
    }

    public function rebuild(): void
    {
        tenancy()->central(fn () => TenantUserDirectory::query()->delete());

        Tenant::query()->get()->each(function (Tenant $tenant): void {
            try {
                $users = $tenant->run(fn () => TenantUser::query()->get(['id', 'name', 'email']));
            } catch (\Throwable $exception) {
                Log::warning('Tenant user directory rebuild skipped tenant', [
                    'tenant_id' => (string) $tenant->getKey(),
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

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

            if ($rows === []) {
                return;
            }

            tenancy()->central(function () use ($rows): void {
                TenantUserDirectory::query()->upsert(
                    $rows,
                    ['tenant_id', 'tenant_user_id'],
                    ['email_normalized', 'user_name', 'active', 'updated_at']
                );
            });
        });
    }
}
