<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\TerrenoImportStatus;
use App\Exceptions\TerrenoImportException;
use App\Models\Central\Tenant;
use App\Models\Tenant\TerrenoImport;
use App\Repositories\Tenant\TerrenoImportReferenceRepository;
use App\Repositories\Tenant\TerrenoImportRepository;
use App\Services\PlanMatrixService;
use App\Services\UsageMetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TerrenoImportCommitService
{
    public function __construct(
        private readonly TerrenoImportRepository $imports,
        private readonly TerrenoImportReferenceRepository $references,
        private readonly TerrenoService $terrains,
        private readonly UsageMetricsService $usage,
        private readonly PlanMatrixService $planMatrix,
    ) {}

    public function commit(int $importId): void
    {
        $import = $this->imports->findById($importId);
        if (! $import instanceof TerrenoImport || $import->status !== TerrenoImportStatus::IMPORTING) {
            return;
        }

        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            throw new TerrenoImportException('TENANT_CONTEXT_REQUIRED', 'Contexto do tenant não inicializado.');
        }

        $lock = Cache::lock("plan-limit:{$tenant->getTenantKey()}:terrenos", 650);
        if (! $lock->get()) {
            throw new TerrenoImportException(
                'PLAN_LIMIT_CHECK_BUSY',
                'Não foi possível adquirir o lock do limite de terrenos.',
            );
        }

        try {
            $rows = $this->imports->validRows($importId);
            $currentCount = $this->usage->getTerrenoCount();
            if (! $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'terrenos')) {
                $limit = $this->planMatrix->getLimitForTenant($tenant, 'terrenos');
                if (($currentCount + $rows->count()) > $limit) {
                    throw new TerrenoImportException(
                        'PLAN_LIMIT_EXCEEDED',
                        'A importação excede o limite de terrenos do plano.',
                        403,
                        ['current' => $currentCount, 'incoming' => $rows->count(), 'limit' => $limit],
                    );
                }
            }

            DB::transaction(function () use ($import, $rows): void {
                $terrainIds = [];
                foreach ($rows as $row) {
                    /** @var array<string, mixed> $data */
                    $data = $row->normalized_data;
                    if ($this->references->terrainDuplicateExists(
                        (string) $data['nome'],
                        isset($data['cidade_code']) ? (string) $data['cidade_code'] : null,
                        isset($data['endereco']) ? (string) $data['endereco'] : null,
                    )) {
                        throw new TerrenoImportException(
                            'TERRAIN_IMPORT_DUPLICATE',
                            "Foi encontrado um terreno duplicado ao confirmar a linha {$row->row_number}.",
                            409,
                            ['row_number' => $row->row_number],
                        );
                    }
                    $terrain = $this->terrains->createImported($data, $import->requester);
                    $terrainIds[$row->id] = $terrain->id;
                }
                $this->imports->markCompleted($import, $terrainIds);
            });
        } finally {
            $lock->release();
        }
    }
}
