<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Legalizacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LegalizacaoInsightRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateControlCenter(array $filters): LengthAwarePaginator
    {
        $query = Legalizacao::query()
            ->with(['terreno', 'responsavel', 'etapas', 'pendencias'])
            ->withCount(['etapas', 'pendencias'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($builder) use ($search): void {
                $builder->where('nome', 'like', "%{$search}%")
                    ->orWhereHas('terreno', fn ($terrainQuery) => $terrainQuery->where('nome', 'like', "%{$search}%"));
            }));

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function forInsights(int $id): Legalizacao
    {
        return Legalizacao::query()
            ->with(['etapas', 'etapas.pendencias', 'etapas.documentos', 'dependencias'])
            ->findOrFail($id);
    }
}
