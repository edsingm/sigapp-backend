<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjetoRepositoryInterface
{
    public function findById(int $id): ?Projeto;

    public function findWithRelations(int $id): ?Projeto;

    public function paginate(int $perPage): LengthAwarePaginator;

    public function listWithFilters(array $filters): LengthAwarePaginator;

    public function listTerrenosElegiveis(array $filters): LengthAwarePaginator;

    /**
     * Lista terrenos com contrato assinado e sem projeto ativo, prontos para iniciar projeto.
     *
     * @param  array{search?: string|null, per_page?: int}  $filters
     */
    public function paginateTerrenosParaNovoProjeto(array $filters): LengthAwarePaginator;

    public function create(array $data): Projeto;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Projeto $projeto, array $data): Projeto;

    /**
     * Força a atualização de atributos (bypass de fillable) e persiste.
     *
     * @param  array<string, mixed>  $data
     */
    public function forceUpdate(Projeto $projeto, array $data): Projeto;

    /**
     * Carrega relações ausentes no modelo.
     *
     * @param  array<int, string>  $relations
     */
    public function loadMissing(Projeto $projeto, array $relations): Projeto;

    public function findTerrenoElegivel(int $terrenoId): Terreno;

    public function existsActiveProjetoForTerreno(int $terrenoId): bool;

    public function findWithFullRelations(int $id): ?Projeto;
}
