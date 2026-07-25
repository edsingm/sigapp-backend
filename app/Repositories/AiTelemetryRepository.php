<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\AiRequestLog;
use App\Repositories\Contracts\AiTelemetryRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AiTelemetryRepository implements AiTelemetryRepositoryInterface
{
    public function create(array $data): AiRequestLog
    {
        return AiRequestLog::create($data);
    }

    public function getCostByUser(int $userId, Carbon $from, ?Carbon $to = null): float
    {
        $to ??= now();

        return (float) AiRequestLog::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('estimated_cost_usd');
    }

    public function getCurrentMonthCost(): float
    {
        $monthStart = now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();

        return (float) AiRequestLog::query()
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonthStart)
            ->sum('estimated_cost_usd');
    }

    public function getLogsBetween(Carbon $from, ?Carbon $to = null): Collection
    {
        $to ??= now();

        /** @var Collection<int, AiRequestLog> $logs */
        $logs = AiRequestLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return $logs;
    }
}
