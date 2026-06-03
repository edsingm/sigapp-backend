<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Viabilidade;
use App\Models\Tenant\ViabilidadeAprovacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ViabilidadeRepositoryInterface
{
    public function findOrFail(int|string $id): Viabilidade;

    public function findWithTrashedOrFail(int|string $id): Viabilidade;

    public function create(array $data): Viabilidade;

    public function update(Viabilidade $viabilidade, array $data): Viabilidade;

    public function delete(Viabilidade $viabilidade): bool;

    public function restore(Viabilidade $viabilidade): Viabilidade;

    public function terrenoExists(int|string $id): bool;

    public function nextVersionForTerreno(int $terrenoId): int;

    public function clearCurrentForTerreno(int $terrenoId): void;

    /**
     * @param  array{search?: string|null, terreno_id?: int|string|null, per_page?: int|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;

    /**
     * @return Collection<int, Viabilidade>
     */
    public function listByTerreno(int $terrenoId): Collection;

    public function latestByTerreno(int $terrenoId): ?Viabilidade;

    public function createApproval(Viabilidade $viabilidade, ?int $userId, string $decision, ?string $comments): ViabilidadeAprovacao;

    public function copySections(Viabilidade $source, Viabilidade $target): void;

    /**
     * @return Collection<int, Viabilidade>
     */
    public function forSelect(?int $terrenoId = null): Collection;

    public function loadDefaultRelations(Viabilidade $viabilidade): Viabilidade;

    public function loadDreRelations(Viabilidade $viabilidade): Viabilidade;

    /**
     * @param  list<string>  $relations
     */
    public function load(Viabilidade $viabilidade, array $relations): Viabilidade;
}
