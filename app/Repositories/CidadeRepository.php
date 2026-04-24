<?php

namespace App\Repositories;

use App\Models\Central\Cidade;
use Illuminate\Database\Eloquent\Collection;

class CidadeRepository
{
    /**
     * @return Collection<int, Cidade>
     */
    public function listStates(): Collection
    {
        return Cidade::states()->get();
    }

    /**
     * @return Collection<int, Cidade>
     */
    public function listByState(string $stateCode): Collection
    {
        return Cidade::citiesByState($stateCode)->get(['code', 'city as name']);
    }

    /**
     * @return Collection<int, Cidade>
     */
    public function searchByTerm(string $term): Collection
    {
        $query = Cidade::query()
            ->select(['code', 'city', 'state', 'state_code'])
            ->orderBy('city')
            ->limit(100);

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->whereRaw('unaccent(city) ILIKE unaccent(?)', ["%{$term}%"]);
        } else {
            $query->whereRaw('lower(city) like lower(?)', ["%{$term}%"]);
        }

        return $query->get();
    }

    public function findByCode(string $cityCode): ?Cidade
    {
        return Cidade::query()->where('code', $cityCode)->first();
    }
}
