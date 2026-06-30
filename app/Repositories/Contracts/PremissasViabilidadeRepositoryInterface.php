<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\PremissasViabilidade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PremissasViabilidadeRepositoryInterface
{
    public function paginate(?string $perfil, int $perPage, ?bool $ativo = null): LengthAwarePaginator;

    public function findById(int $id): ?PremissasViabilidade;

    public function findPreviousVersion(PremissasViabilidade $premissa): ?PremissasViabilidade;

    /**
     * @return list<PremissasViabilidade>
     */
    public function listVersions(PremissasViabilidade $premissa): array;

    public function create(array $data): PremissasViabilidade;

    public function nextVersion(string $perfil): int;

    public function closeCurrentVersion(PremissasViabilidade $premissa, string $encerradaEm): void;

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade;

    public function delete(PremissasViabilidade $premissa): void;
}
