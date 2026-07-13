<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Jobs\GenerateReportRunJob;
use App\Models\Tenant\ReportRun;
use App\Models\Tenant\User;
use App\Repositories\Tenant\ReportRunRepository;

class ReportRunService
{
    public function __construct(
        private readonly ReportRunRepository $repository,
        private readonly ReportTemplateService $templates,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): ReportRun
    {
        $existing = $this->repository->findByIdempotencyKey($user, (string) $data['idempotency_key']);
        if ($existing) {
            return $existing;
        }

        $template = $this->templates->find($user, (int) $data['template_id']);
        $run = $this->repository->create([
            'report_template_id' => $template->id,
            'requested_by' => $user->id,
            'idempotency_key' => $data['idempotency_key'],
            'definition_snapshot' => $template->definition,
            'filters' => $data['filters'] ?? [],
            'format' => $data['format'] ?? 'csv',
            'status' => 'pending',
            'progress' => 0,
            'requested_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        GenerateReportRunJob::dispatch($run->id);

        return $run->load('template');
    }

    public function find(User $user, int $id): ReportRun
    {
        return $this->repository->findForUser($user, $id);
    }
}
