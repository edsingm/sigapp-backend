<?php

namespace App\Repositories;

use App\Models\Central\Cidade;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection;

class CidadeRepository
{
    /**
     * @return Collection<int, Cidade>
     */
    public function listStates(): Collection
    {
        /** @var Collection<int, Cidade> $states */
        $states = Cidade::query()
            ->select(['state_code', 'state'])
            ->distinct()
            ->orderBy('state')
            ->get();

        return $states;
    }

    /**
     * @return Collection<int, Cidade>
     */
    public function listByState(string $stateCode): Collection
    {
        /** @var Collection<int, Cidade> $cities */
        $cities = Cidade::query()
            ->where('state_code', $stateCode)
            ->orderBy('city')
            ->get(['code', 'city', 'state', 'state_code']);

        return $cities;
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

        /** @var Connection $connection */
        $connection = $query->getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $query->whereRaw('unaccent(city) ILIKE unaccent(?)', ["%{$term}%"]);
        } else {
            $query->whereRaw('lower(city) like lower(?)', ["%{$term}%"]);
        }

        /** @var Collection<int, Cidade> $cities */
        $cities = $query->get();

        return $cities;
    }

    public function findByCode(string $cityCode): ?Cidade
    {
        return Cidade::query()->where('code', $cityCode)->first();
    }
}
