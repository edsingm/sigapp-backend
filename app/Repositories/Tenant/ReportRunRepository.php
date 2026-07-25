<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ReportRun;
use App\Models\Tenant\User;

class ReportRunRepository
{
    private const STALE_AFTER_MINUTES = 10;

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

    public function claimPending(int $id): ?ReportRun
    {
        $claimed = ReportRun::query()
            ->whereKey($id)
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere(function ($query): void {
                        $query->where('status', 'running')
                            ->where('updated_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES));
                    });
            })
            ->update([
                'status' => 'running',
                'progress' => 10,
                'error_message' => null,
                'updated_at' => now(),
            ]);

        return $claimed === 1
            ? ReportRun::query()->find($id)
            : null;
    }

    public function releaseForRetry(int $id): void
    {
        ReportRun::query()
            ->whereKey($id)
            ->where('status', 'running')
            ->update([
                'status' => 'pending',
                'progress' => 0,
                'updated_at' => now(),
            ]);
    }

    public function markFailed(int $id): void
    {
        ReportRun::query()
            ->whereKey($id)
            ->whereIn('status', ['pending', 'running'])
            ->update([
                'status' => 'failed',
                'progress' => 0,
                'error_message' => 'Não foi possível gerar o relatório. Tente novamente.',
                'updated_at' => now(),
            ]);
    }

    /** @param array<string, mixed> $data */
    public function update(ReportRun $run, array $data): ReportRun
    {
        $run->update($data);

        return $run->fresh('template') ?? $run;
    }
}
