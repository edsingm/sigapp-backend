<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\TenantExportStatus;
use App\Enums\TenantExportType;
use App\Jobs\GenerateTenantExportJob;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\User;
use App\Repositories\Contracts\TerrenoExportRepositoryInterface;
use App\Repositories\Contracts\ViabilidadeRepositoryInterface;
use App\Repositories\Tenant\TenantExportGenerationRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TenantExportGenerationService
{
    public function __construct(
        private readonly TenantExportGenerationRepository $repository,
        private readonly TerrenoExportRepositoryInterface $terrenos,
        private readonly ViabilidadeRepositoryInterface $viabilidades,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): TenantExportGeneration
    {
        $type = TenantExportType::from((string) $data['type']);
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;

        $this->ensureSubjectExists($type, $subjectId);

        $generation = $this->repository->createOrFind(
            $user,
            (string) $data['idempotency_key'],
            [
                'type' => $type,
                'subject_id' => $subjectId,
                'filters' => $type->acceptsFilters() ? ($data['filters'] ?? []) : [],
                'payload' => $type->acceptsPayload() ? ($data['payload'] ?? []) : [],
                'status' => TenantExportStatus::QUEUED,
                'progress' => 0,
                'requested_at' => now(),
                'expires_at' => now()->addHours(24),
            ],
        );

        if ($generation->wasRecentlyCreated) {
            GenerateTenantExportJob::dispatch($generation->id);
        }

        return $generation;
    }

    public function find(User $user, int $id): TenantExportGeneration
    {
        return $this->repository->findForUser($user, $id);
    }

    private function ensureSubjectExists(TenantExportType $type, ?int $subjectId): void
    {
        if (! $type->requiresSubject()) {
            return;
        }

        if ($subjectId === null) {
            throw (new ModelNotFoundException)->setModel(
                $type->authorizableModel(),
            );
        }

        if ($type === TenantExportType::VIABILIDADE_PDF) {
            $this->viabilidades->findOrFail($subjectId);

            return;
        }

        if (! $this->terrenos->exists($subjectId)) {
            throw (new ModelNotFoundException)->setModel(
                $type->authorizableModel(),
                [$subjectId],
            );
        }
    }
}
