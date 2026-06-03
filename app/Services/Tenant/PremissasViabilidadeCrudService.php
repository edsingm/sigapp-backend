<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\PremissasViabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PremissasViabilidadeCrudService
{
    public function __construct(
        private readonly PremissasViabilidadeRepositoryInterface $repository,
    ) {}

    public function list(?string $perfil, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginate($perfil, $perPage);
    }

    public function findById(int $id): ?PremissasViabilidade
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): PremissasViabilidade
    {
        return $this->repository->create($data);
    }

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade
    {
        return $this->repository->update($premissa, $data);
    }

    public function delete(PremissasViabilidade $premissa): void
    {
        $this->repository->delete($premissa);
    }
}
