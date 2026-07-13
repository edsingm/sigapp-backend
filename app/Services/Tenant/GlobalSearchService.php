<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Repositories\Tenant\GlobalSearchRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use RuntimeException;

class GlobalSearchService
{
    private const TYPES = ['terreno', 'viabilidade', 'comite', 'negociacao', 'legalizacao', 'projeto', 'pessoa'];

    /** @var array<string, class-string> */
    private const MODELS = [
        'terreno' => Terreno::class,
        'viabilidade' => Viabilidade::class,
        'comite' => ComiteRevisao::class,
        'negociacao' => Negociacao::class,
        'legalizacao' => Legalizacao::class,
        'projeto' => Projeto::class,
        'pessoa' => User::class,
    ];

    public function __construct(
        private readonly GlobalSearchRepository $repository,
    ) {}

    /** @param array<int, string> $requestedTypes */
    public function search(User $user, string $query, array $requestedTypes, int $limit): LengthAwarePaginator
    {
        $types = $requestedTypes === [] ? self::TYPES : array_values(array_intersect(self::TYPES, $requestedTypes));
        if ($types === []) {
            throw new RuntimeException('Nenhum tipo de busca permitido.');
        }

        $authorizedTypes = array_values(array_filter($types, fn (string $type): bool => $user->can('viewAny', self::MODELS[$type])));
        $rows = $this->repository->search($authorizedTypes, $query, min(50, max(1, $limit)));
        $collection = new Collection($rows);

        return new Paginator($collection, $collection->count(), $limit, 1, ['path' => '/api/v1/search']);
    }
}
