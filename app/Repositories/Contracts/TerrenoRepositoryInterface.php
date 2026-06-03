<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoInfos;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TerrenoRepositoryInterface
{
    public function findById(int|string $id): ?Terreno;

    public function findOrFail(int|string $id): Terreno;

    public function findInfoOrFail(int|string $id): TerrenoInfos;

    /**
     * @param  array{search?: string|null, per_page?: int, page?: int}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;

    public function create(array $data): Terreno;

    public function update(Terreno $terreno, array $data): Terreno;

    public function delete(Terreno $terreno): void;

    /**
     * @return Collection<int, Terreno>
     */
    public function listForSelect(): Collection;

    public function createInfo(Terreno $terreno, array $data): TerrenoInfos;

    /**
     * @return Collection<int, TerrenoInfos>
     */
    public function listInfos(Terreno $terreno): Collection;

    public function updateInfo(TerrenoInfos $info, array $data): TerrenoInfos;

    public function deleteInfo(TerrenoInfos $info): void;

    public function loadDetailRelations(Terreno $terreno): Terreno;

    public function loadWorkflowRelations(Terreno $terreno): Terreno;
}
