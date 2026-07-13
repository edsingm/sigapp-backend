<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\EntityActivity;
use App\Repositories\Tenant\EntityActivityRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ActivityService
{
    private const ENTITY_TYPES = [
        'terreno',
        'viabilidade',
        'comite',
        'negociacao',
        'contrato',
        'legalizacao',
        'legalizacao_etapa',
        'projeto',
        'documento',
        'relatorio',
    ];

    private const ACTIVITY_TYPES = [
        'created',
        'updated',
        'commented',
        'status_changed',
        'mentioned',
        'assigned',
        'approved',
        'rejected',
        'generated',
    ];

    public function __construct(
        private readonly EntityActivityRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, EntityActivity>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        if (isset($filters['entity_type'])) {
            $this->assertEntityType((string) $filters['entity_type']);
        }

        if (isset($filters['types'])) {
            $invalid = array_diff($filters['types'], self::ACTIVITY_TYPES);
            if ($invalid !== []) {
                throw new InvalidArgumentException('Tipo de atividade inválido.');
            }
        }

        return $this->repository->paginate($filters);
    }

    public function assertEntityType(string $entityType): void
    {
        if (! in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de entidade inválido.');
        }
    }
}
