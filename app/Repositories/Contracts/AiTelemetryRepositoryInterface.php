<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\AiRequestLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface AiTelemetryRepositoryInterface
{
    public function create(array $data): AiRequestLog;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiRequestLog $log, array $data): AiRequestLog;

    public function expireStaleReservations(CarbonInterface $before): int;

    public function getCostByUser(int $userId, Carbon $from, ?Carbon $to = null): float;

    public function getCurrentMonthCost(): float;

    /**
     * @return Collection<int, AiRequestLog>
     */
    public function getLogsBetween(Carbon $from, ?Carbon $to = null): Collection;
}
