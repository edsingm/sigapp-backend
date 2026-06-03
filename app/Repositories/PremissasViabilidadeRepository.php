<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\PremissasViabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PremissasViabilidadeRepository implements PremissasViabilidadeRepositoryInterface
{
    public function paginate(?string $perfil, int $perPage): LengthAwarePaginator
    {
        $query = PremissasViabilidade::query();

        if ($perfil !== null && $perfil !== '') {
            $query->where('perfil_financiamento', $perfil);
        }

        return $query
            ->orderBy('perfil_financiamento')
            ->orderBy('versao', 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?PremissasViabilidade
    {
        return PremissasViabilidade::query()->find($id);
    }

    public function create(array $data): PremissasViabilidade
    {
        return PremissasViabilidade::query()->create($data);
    }

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade
    {
        $premissa->update($data);

        return $premissa->refresh();
    }

    public function delete(PremissasViabilidade $premissa): void
    {
        $premissa->delete();
    }
}
