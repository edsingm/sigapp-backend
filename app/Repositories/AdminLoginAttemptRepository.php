<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AdminLoginAttempt;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminLoginAttemptRepository
{
    /**
     * @param  array{
     *     successful?: bool|null,
     *     email?: string|null,
     *     ip?: string|null,
     *     from?: string|null,
     *     to?: string|null
     * }  $filters
     * @return LengthAwarePaginator<int, AdminLoginAttempt>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = AdminLoginAttempt::query()
            ->with('user:id,name,email')
            ->latest('id');

        if (array_key_exists('successful', $filters) && $filters['successful'] !== null) {
            $query->where('successful', $filters['successful']);
        }

        if (! empty($filters['email'])) {
            $query->where('email', 'like', '%'.mb_strtolower(trim($filters['email'])).'%');
        }

        if (! empty($filters['ip'])) {
            $query->where('ip_address', 'like', '%'.trim($filters['ip']).'%');
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }
}
