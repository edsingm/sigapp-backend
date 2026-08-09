<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogRepository
{
    /**
     * @param  array{
     *     action?: string|null,
     *     user_id?: int|null,
     *     from?: string|null,
     *     to?: string|null,
     *     ip?: string|null,
     *     per_page?: int
     * }  $filters
     */
    public function paginateWithFilters(
        ?string $action = null,
        ?int $userId = null,
        int $perPage = 20,
        array $filters = []
    ): LengthAwarePaginator {
        $query = AuditLog::query()->with('user');

        $actionFilter = $filters['action'] ?? $action;
        if ($actionFilter !== null && $actionFilter !== '') {
            if (str_contains($actionFilter, '*') || ! str_contains($actionFilter, '_')) {
                // Prefix match for "tenant" or "tenant.*"
                $prefix = rtrim((string) $actionFilter, '*');
                if (str_ends_with($prefix, '.')) {
                    $query->where('action', 'LIKE', $prefix.'%');
                } elseif (! str_contains($prefix, '.')) {
                    $query->where(function ($q) use ($prefix): void {
                        $q->where('action', $prefix)
                            ->orWhere('action', 'LIKE', $prefix.'.%');
                    });
                } else {
                    $query->where('action', 'LIKE', rtrim($prefix, '*').'%');
                }
            } else {
                $query->where('action', $actionFilter);
            }
        }

        $userFilter = $filters['user_id'] ?? $userId;
        if ($userFilter !== null) {
            $query->where('user_id', $userFilter);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['ip'])) {
            $query->where('ip_address', 'like', '%'.trim((string) $filters['ip']).'%');
        }

        return $query
            ->latest()
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return list<string>
     */
    public function distinctActions(int $limit = 50): array
    {
        return array_values(AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->limit($limit)
            ->pluck('action')
            ->filter(static fn (mixed $action): bool => $action !== null && $action !== '')
            ->map(static fn (mixed $action): string => (string) $action)
            ->values()
            ->all());
    }
}
