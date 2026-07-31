<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Jobs\GenerateReportRunJob;
use App\Models\Tenant\ReportSchedule;
use App\Models\Tenant\User;
use App\Repositories\Tenant\ReportRunRepository;
use App\Repositories\Tenant\ReportScheduleRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportScheduleService
{
    /** @var list<string> */
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public function __construct(
        private readonly ReportScheduleRepository $repository,
        private readonly ReportTemplateService $templates,
        private readonly ReportRunRepository $runs,
        private readonly ReportCatalogService $catalog,
        private readonly ReportRunService $runService,
    ) {}

    /** @return Collection<int, ReportSchedule> */
    public function list(User $user): Collection
    {
        return $this->repository->listForUser($user);
    }

    public function find(User $user, int $id): ReportSchedule
    {
        return $this->repository->findForUser($user, $id);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): ReportSchedule
    {
        $template = $this->templates->find($user, (int) $data['template_id']);
        $frequency = $this->normalizeFrequency((string) $data['frequency']);
        $format = $this->normalizeFormat((string) ($data['format'] ?? 'xlsx'));
        $this->runService->assertFormatAllowed($format);

        return $this->repository->create([
            'report_template_id' => $template->id,
            'owner_id' => $user->id,
            'name' => $data['name'],
            'frequency' => $frequency,
            'format' => $format,
            'filters' => $data['filters'] ?? [],
            'notify_email' => (bool) ($data['notify_email'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'next_run_at' => $this->computeNextRunAt($frequency, now()),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, int $id, array $data): ReportSchedule
    {
        $schedule = $this->find($user, $id);
        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }
        if (array_key_exists('template_id', $data)) {
            $template = $this->templates->find($user, (int) $data['template_id']);
            $updates['report_template_id'] = $template->id;
        }
        if (array_key_exists('frequency', $data)) {
            $updates['frequency'] = $this->normalizeFrequency((string) $data['frequency']);
            $updates['next_run_at'] = $this->computeNextRunAt($updates['frequency'], now());
        }
        if (array_key_exists('format', $data)) {
            $format = $this->normalizeFormat((string) $data['format']);
            $this->runService->assertFormatAllowed($format);
            $updates['format'] = $format;
        }
        if (array_key_exists('filters', $data)) {
            $updates['filters'] = is_array($data['filters']) ? $data['filters'] : [];
        }
        if (array_key_exists('notify_email', $data)) {
            $updates['notify_email'] = (bool) $data['notify_email'];
        }
        if (array_key_exists('is_active', $data)) {
            $updates['is_active'] = (bool) $data['is_active'];
            if ($updates['is_active'] && $schedule->next_run_at === null) {
                $updates['next_run_at'] = $this->computeNextRunAt(
                    (string) ($updates['frequency'] ?? $schedule->frequency),
                    now(),
                );
            }
        }

        return $this->repository->update($schedule, $updates);
    }

    public function delete(User $user, int $id): void
    {
        $this->repository->delete($this->find($user, $id));
    }

    /**
     * Dispara runs devidos no tenant atual.
     *
     * @return int quantidade de runs enfileirados
     */
    public function dispatchDue(): int
    {
        $dispatched = 0;
        foreach ($this->repository->dueSchedules() as $schedule) {
            if ($schedule->template === null || $schedule->owner === null) {
                continue;
            }

            $nextRunAt = $this->computeNextRunAt($schedule->frequency, now());
            if (! $this->repository->claimDue($schedule->id, $nextRunAt)) {
                continue;
            }

            $run = $this->runs->create([
                'report_template_id' => $schedule->report_template_id,
                'report_schedule_id' => $schedule->id,
                'requested_by' => $schedule->owner_id,
                'idempotency_key' => (string) Str::uuid(),
                'definition_snapshot' => $schedule->template->definition,
                'filters' => $schedule->filters ?? [],
                'format' => $schedule->format,
                'status' => 'pending',
                'progress' => 0,
                'requested_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);

            $this->repository->update($schedule->fresh() ?? $schedule, [
                'last_run_id' => $run->id,
            ]);

            GenerateReportRunJob::dispatch($run->id);
            $dispatched++;
        }

        return $dispatched;
    }

    public function computeNextRunAt(string $frequency, CarbonInterface $from): CarbonInterface
    {
        return match ($frequency) {
            'weekly' => $from->copy()->addWeek()->startOfHour(),
            'monthly' => $from->copy()->addMonthNoOverflow()->startOfHour(),
            default => $from->copy()->addDay()->startOfHour(),
        };
    }

    private function normalizeFrequency(string $frequency): string
    {
        if (! in_array($frequency, self::FREQUENCIES, true)) {
            throw ValidationException::withMessages([
                'frequency' => ['Frequência inválida. Use daily, weekly ou monthly.'],
            ]);
        }

        return $frequency;
    }

    private function normalizeFormat(string $format): string
    {
        if (! in_array($format, $this->catalog->formatKeys(), true)) {
            throw ValidationException::withMessages([
                'format' => ['Formato de exportação não permitido.'],
            ]);
        }

        return $format;
    }
}
