<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Pagination\LengthAwarePaginator;

class DocumentoRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Documento>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sortDirection = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Documento::query()
            ->with(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name']);

        if (! empty($filters['terreno_id'])) {
            $query->where('terreno_id', $filters['terreno_id']);
        }

        if (! empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (! empty($filters['categoria'])) {
            $query->where('categoria', $filters['categoria']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $query->where('nome', 'like', '%'.$filters['search'].'%');
        }

        return $query
            ->orderBy((string) ($filters['sort_by'] ?? 'created_at'), $sortDirection)
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function findOrFail(int|string $id, array $relations = []): Documento
    {
        $query = Documento::query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Documento
    {
        return Documento::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Documento $documento, array $data): Documento
    {
        $documento->update($data);

        return $documento->fresh(['terreno:id,nome', 'createdBy:id,name', 'updatedBy:id,name']) ?? $documento;
    }

    public function delete(Documento $documento): bool
    {
        return (bool) $documento->delete();
    }
}
