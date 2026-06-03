<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\AiRequestLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface AiTelemetryRepositoryInterface
{
    public function create(array $data): AiRequestLog;

    public function getCostByUser(int $userId, Carbon $from, ?Carbon $to = null): float;

    public function getCurrentMonthCost(): float;

    /**
     * @return Collection<int, AiRequestLog>
     */
    public function getLogsBetween(Carbon $from, ?Carbon $to = null): Collection;
}
