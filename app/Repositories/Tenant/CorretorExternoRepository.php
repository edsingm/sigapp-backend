<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\CorretorExterno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CorretorExternoRepository
{
    public function findById(int|string $id): CorretorExterno
    {
        return CorretorExterno::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CorretorExterno
    {
        return CorretorExterno::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CorretorExterno $corretor, array $data): CorretorExterno
    {
        $corretor->update($data);

        return $corretor;
    }

    public function delete(CorretorExterno $corretor): bool
    {
        return $corretor->delete() !== false;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CorretorExterno>
     */
    public function paginate(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $query = CorretorExterno::query();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $query->orderBy('nome', 'asc');

        return $query->paginate($perPage);
    }

    public function listForSelect(): Collection
    {
        return CorretorExterno::orderBy('nome', 'asc')
            ->get(['id', 'nome']);
    }
}
