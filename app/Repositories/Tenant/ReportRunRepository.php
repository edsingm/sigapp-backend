<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ReportRun;
use App\Models\Tenant\User;

class ReportRunRepository
{
    public function findForUser(User $user, int $id): ReportRun
    {
        return ReportRun::query()
            ->whereKey($id)
            ->where('requested_by', $user->id)
            ->with('template')
            ->firstOrFail();
    }

    public function findByIdempotencyKey(User $user, string $key): ?ReportRun
    {
        return ReportRun::query()
            ->where('requested_by', $user->id)
            ->where('idempotency_key', $key)
            ->with('template')
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): ReportRun
    {
        return ReportRun::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(ReportRun $run, array $data): ReportRun
    {
        $run->update($data);

        return $run->fresh('template') ?? $run;
    }
}
