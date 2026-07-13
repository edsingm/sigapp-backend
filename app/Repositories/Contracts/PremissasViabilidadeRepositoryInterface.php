<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\PremissasViabilidade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    /**
     * Ajusta encerrada_em da premissa vigente anterior (véspera da nova).
     * Não desativa antes da hora se a nova só começa no futuro.
     */
    public function closeIntervalBefore(string $perfil, string $novaVigenteEm, ?int $exceptId = null): void;

    /**
     * @return Collection<int, PremissasViabilidade>
     */
    public function findOverlapping(string $perfil, string $vigenteEm, ?string $encerradaEm, ?int $exceptId = null): Collection;

    public function findActiveForPerfilAt(string $perfil, ?string $date = null): ?PremissasViabilidade;

    public function isReferencedInSnapshots(int $premissaId): bool;

    public function deactivate(PremissasViabilidade $premissa, ?string $encerradaEm = null): PremissasViabilidade;

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade;

    public function delete(PremissasViabilidade $premissa): void;
}
