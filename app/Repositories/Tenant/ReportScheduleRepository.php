<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ReportSchedule;
use App\Models\Tenant\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class ReportScheduleRepository
{
    /** @return Collection<int, ReportSchedule> */
    public function listForUser(User $user): Collection
    {
        /** @var Collection<int, ReportSchedule> $schedules */
        $schedules = ReportSchedule::query()
            ->where('owner_id', $user->id)
            ->with(['template:id,name,is_system', 'lastRun:id,status,completed_at,format'])
            ->orderByDesc('is_active')
            ->orderBy('next_run_at')
            ->get();

        return $schedules;
    }

    public function findForUser(User $user, int $id): ReportSchedule
    {
        return ReportSchedule::query()
            ->whereKey($id)
            ->where('owner_id', $user->id)
            ->with(['template', 'lastRun'])
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): ReportSchedule
    {
        return ReportSchedule::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(ReportSchedule $schedule, array $data): ReportSchedule
    {
        $schedule->update($data);

        return $schedule->fresh(['template', 'lastRun']) ?? $schedule;
    }

    public function delete(ReportSchedule $schedule): void
    {
        $schedule->delete();
    }

    /** @return Collection<int, ReportSchedule> */
    public function dueSchedules(): Collection
    {
        /** @var Collection<int, ReportSchedule> $schedules */
        $schedules = ReportSchedule::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->with(['template', 'owner'])
            ->orderBy('id')
            ->limit(200)
            ->get();

        return $schedules;
    }

    /**
     * Claim atômico: avança next_run_at para evitar disparo duplicado.
     */
    public function claimDue(int $id, CarbonInterface|\DateTimeInterface|string $nextRunAt): bool
    {
        $claimed = ReportSchedule::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->update([
                'next_run_at' => $nextRunAt,
                'last_run_at' => now(),
                'updated_at' => now(),
            ]);

        return $claimed === 1;
    }
}
