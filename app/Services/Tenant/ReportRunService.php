<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Jobs\GenerateReportRunJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\ReportRun;
use App\Models\Tenant\User;
use App\Repositories\Tenant\ReportRunRepository;
use App\Services\PlanMatrixService;
use Illuminate\Validation\ValidationException;

class ReportRunService
{
    public function __construct(
        private readonly ReportRunRepository $repository,
        private readonly ReportTemplateService $templates,
        private readonly ReportCatalogService $catalog,
        private readonly PlanMatrixService $planMatrix,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): ReportRun
    {
        $existing = $this->repository->findByIdempotencyKey($user, (string) $data['idempotency_key']);
        if ($existing) {
            return $existing;
        }

        $format = (string) ($data['format'] ?? 'csv');
        if (! in_array($format, $this->catalog->formatKeys(), true)) {
            throw ValidationException::withMessages([
                'format' => ['Formato de exportação não permitido.'],
            ]);
        }
        $this->assertFormatAllowed($format);

        $template = $this->templates->find($user, (int) $data['template_id']);
        $run = $this->repository->create([
            'report_template_id' => $template->id,
            'requested_by' => $user->id,
            'idempotency_key' => $data['idempotency_key'],
            'definition_snapshot' => $template->definition,
            'filters' => $data['filters'] ?? [],
            'format' => $format,
            'status' => 'pending',
            'progress' => 0,
            'requested_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        GenerateReportRunJob::dispatch($run->id, $run->idempotency_key);

        return $run->load(['template', 'requester']);
    }

    public function find(User $user, int $id): ReportRun
    {
        return $this->repository->findForUser($user, $id);
    }

    /**
     * Gate de formato: xlsx exige exports.excel; pdf exige exports.pdf.
     * Em testes/sem plano resolvido o gate é ignorado (reports.builder já protege a rota).
     */
    public function assertFormatAllowed(string $format): void
    {
        $feature = $this->catalog->featureForFormat($format);
        if ($feature === null) {
            return;
        }

        if (! tenancy()->initialized) {
            return;
        }

        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant || $tenant->plan === null) {
            return;
        }

        if (! $this->planMatrix->hasFeatureForTenant($tenant, $feature)) {
            throw ValidationException::withMessages([
                'format' => ["Seu plano não inclui a feature {$feature} para este formato."],
            ]);
        }
    }
}
