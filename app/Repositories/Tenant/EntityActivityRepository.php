<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\EntityActivity;
use Illuminate\Pagination\LengthAwarePaginator;

class EntityActivityRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, EntityActivity>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = EntityActivity::query()
            ->with(['user', 'terreno'])
            ->latest('happened_at')
            ->latest('id');

        if (isset($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (isset($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (isset($filters['actor_id'])) {
            $query->where('user_id', $filters['actor_id']);
        }

        if (isset($filters['types'])) {
            $query->whereIn('action', $filters['types']);
        }

        if (isset($filters['occurred_after'])) {
            $query->where('happened_at', '>=', $filters['occurred_after']);
        }

        if (isset($filters['occurred_before'])) {
            $query->where('happened_at', '<=', $filters['occurred_before']);
        }

        return $query->paginate(
            (int) ($filters['per_page'] ?? 30),
            ['*'],
            'page',
            (int) ($filters['page'] ?? 1),
        );
    }
}
