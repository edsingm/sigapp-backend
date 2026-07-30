<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Entitlement;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UsageMetricsRepositoryInterface;
use App\Services\EntitlementValueService;
use App\Services\PlanMatrixService;
use App\Support\EntitlementCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AuditPlanEntitlementsCommand extends Command
{
    protected $signature = 'plans:audit-entitlements {--tenant= : Audita apenas um tenant por id ou slug}';

    protected $description = 'Audita catálogo, matrizes, aliases, overrides e contabilização de storage sem alterar dados';

    public function handle(
        EntitlementValueService $values,
        PlanMatrixService $matrix,
        UsageMetricsRepositoryInterface $usage,
        EntitlementRepositoryInterface $entitlementRepository,
        PlanRepositoryInterface $planRepository,
        TenantRepositoryInterface $tenantRepository,
    ): int {
        $issues = [];
        $entitlements = $entitlementRepository->all();

        foreach ($entitlements as $entitlement) {
            if (array_key_exists($entitlement->key, EntitlementCatalog::LEGACY_ALIASES)) {
                $issues[] = "alias legado no catálogo: {$entitlement->key}";
            }

            try {
                $values->validateScope($entitlement->type, $entitlement->scope);
                $values->normalize($entitlement->type, $entitlement->key, $entitlement->default_value);
            } catch (Throwable $exception) {
                $issues[] = "entitlement {$entitlement->key}: {$exception->getMessage()}";
            }
        }

        foreach ($planRepository->all() as $plan) {
            $links = DB::table('plan_entitlements')
                ->where('plan_id', $plan->id)
                ->get();

            if ($links->isEmpty()) {
                $issues[] = "plano {$plan->slug} sem matriz";

                continue;
            }

            foreach ($links as $link) {
                $entitlement = $entitlements->firstWhere('id', (int) $link->entitlement_id);
                if (! $entitlement instanceof Entitlement) {
                    $issues[] = "plano {$plan->slug} referencia entitlement inexistente #{$link->entitlement_id}";

                    continue;
                }

                try {
                    $decoded = json_decode((string) $link->value, true, flags: JSON_THROW_ON_ERROR);
                    $values->normalize($entitlement->type, $entitlement->key, $decoded);
                } catch (Throwable $exception) {
                    $issues[] = "plano {$plan->slug}/{$entitlement->key}: {$exception->getMessage()}";
                }
            }

            $resolved = $matrix->resolve($plan);
            if (data_get($resolved, 'features.projects.planning') === true
                && data_get($resolved, 'features.projects.enabled') !== true) {
                $issues[] = "plano {$plan->slug}: projects.planning depende de projects.enabled";
            }
            if (data_get($resolved, 'features.committee.meeting_mode') === true
                && data_get($resolved, 'features.committee.meeting') !== true) {
                $issues[] = "plano {$plan->slug}: committee.meeting_mode depende de committee.meeting";
            }
        }

        $tenantFilter = $this->option('tenant');
        $tenants = $tenantRepository->readyForEntitlementAudit(
            is_string($tenantFilter) ? $tenantFilter : null,
        );

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                foreach ($usage->storageObjects() as $object) {
                    $actualSize = Storage::disk($object['disk'])->exists($object['path'])
                        ? (int) Storage::disk($object['disk'])->size($object['path'])
                        : null;

                    if ($actualSize !== null && ($object['size'] <= 0 || $actualSize !== $object['size'])) {
                        $issues[] = "tenant {$tenant->slug}: arquivo não contabilizado corretamente {$object['disk']}:{$object['path']}";
                    }
                }
            } catch (Throwable $exception) {
                $issues[] = "tenant {$tenant->slug}: auditoria indisponível ({$exception->getMessage()})";
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        if ($issues === []) {
            $this->info('Nenhuma inconsistência encontrada.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn($issue);
        }
        $this->error(count($issues).' inconsistência(s) encontrada(s). Nenhum dado foi alterado.');

        return self::FAILURE;
    }
}
