<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogRepository
{
    public function paginateWithFilters(?string $action, ?int $userId, int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user');

        if ($action !== null) {
            if (str_contains($action, '*') || ! str_contains($action, '_')) {
                $query->where('action', 'LIKE', rtrim($action, '*').'%');
            } else {
                $query->where('action', $action);
            }
        }

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->latest()->paginate($perPage);
    }
}
