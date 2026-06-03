<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\PremissasViabilidade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PremissasViabilidadeRepositoryInterface
{
    public function paginate(?string $perfil, int $perPage): LengthAwarePaginator;

    public function findById(int $id): ?PremissasViabilidade;

    public function create(array $data): PremissasViabilidade;

    public function update(PremissasViabilidade $premissa, array $data): PremissasViabilidade;

    public function delete(PremissasViabilidade $premissa): void;
}
