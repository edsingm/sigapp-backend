<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\Tenant;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Collection;

class TenantStatsRepository
{
    public function countTenants(): int
    {
        return Tenant::query()->count();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function allTenants(): Collection
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->get(['id', 'slug']);

        return $tenants;
    }

    /**
     * Contagens das entidades do tenant atual (deve ser chamado dentro do contexto do tenant).
     *
     * @return array{terrenos: int, projetos: int, usuarios: int}
     */
    public function currentTenantCounts(): array
    {
        return [
            'terrenos' => Terreno::query()->count(),
            'projetos' => Projeto::query()->count(),
            'usuarios' => User::query()->count(),
        ];
    }
}
