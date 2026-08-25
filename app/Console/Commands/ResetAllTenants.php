<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Privacy\TenantLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ResetAllTenants extends Command
{
    private const ALLOWED_ENVIRONMENTS = ['local', 'testing', 'staging'];

    /** @var list<string> */
    private const UNCONSTRAINED_CENTRAL_TABLES = [
        'subscriptions',
        'tenant_user_directories',
        'platform_announcement_dismissals',
        'hiperdados_imports',
    ];

    protected $signature = 'tenants:reset-all
        {--force-staging : Autoriza o reset em staging após confirmação nominal}';

    protected $description = 'Reseta completamente tenants em local/testing; staging exige autorização adicional';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        $environment = app()->environment();

        if (! in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
            $this->error("Reset bloqueado no ambiente [{$environment}]. Apenas local, testing e staging são permitidos.");

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Reset bloqueado: execute o comando em um terminal interativo.');

            return self::FAILURE;
        }

        if ($environment === 'staging') {
            if (! (bool) $this->option('force-staging')) {
                $this->error('Em staging, informe --force-staging e confirme nominalmente a operação.');

                return self::FAILURE;
            }

            if ($this->hasActiveStagingSubscriptions()) {
                $this->error('Reset bloqueado: staging possui assinaturas Stripe potencialmente ativas. Cancele-as no Stripe antes de continuar.');

                return self::FAILURE;
            }

            $confirmation = trim((string) $this->ask('Digite RESET STAGING para apagar todos os tenants deste ambiente'));

            if (! hash_equals('RESET STAGING', $confirmation)) {
                $this->warn('Reset de staging cancelado.');

                return self::SUCCESS;
            }
        } elseif (! $this->confirm('Apagar todos os tenants, schemas, arquivos e vínculos centrais deste ambiente?')) {
            $this->warn('Reset cancelado.');

            return self::SUCCESS;
        }

        $total = Tenant::query()->count();
        $removed = 0;
        $failed = 0;

        Tenant::query()->chunkById(50, function ($tenants) use ($lifecycle, &$removed, &$failed): void {
            foreach ($tenants as $tenant) {
                try {
                    $this->resetTenant($tenant, $lifecycle);
                    $removed++;
                } catch (Throwable $exception) {
                    $failed++;
                    $tenantId = (string) $tenant->getKey();

                    Log::error('Falha ao resetar tenant em ambiente descartável.', [
                        'tenant_id' => $tenantId,
                        'environment' => app()->environment(),
                        'exception' => $exception,
                    ]);

                    $this->error("Tenant [{$tenantId}] não foi removido: {$exception->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info("Reset concluído: {$removed} removido(s), {$failed} falha(s), {$total} processado(s).");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resetTenant(Tenant $tenant, TenantLifecycleService $lifecycle): void
    {
        $tenantId = (string) $tenant->getKey();

        $lifecycle->wipe($tenant, force: true);
        $this->deleteCentralArtifacts($tenantId);

        DB::transaction(function () use ($tenant, $tenantId): void {
            foreach (self::UNCONSTRAINED_CENTRAL_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('tenant_id', $tenantId)->delete();
                }
            }

            // O lifecycle já removeu e verificou o schema. Suprimir o evento evita
            // executar DeleteDatabase uma segunda vez ao excluir o registro central.
            Tenant::withoutEvents(static fn () => $tenant->deleteOrFail());
        });
    }

    private function deleteCentralArtifacts(string $tenantId): void
    {
        if (! Schema::hasTable('hiperdados_imports')) {
            return;
        }

        $artifacts = DB::table('hiperdados_imports')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('storage_disk')
            ->whereNotNull('storage_path')
            ->get(['storage_disk', 'storage_path']);

        foreach ($artifacts as $artifact) {
            $disk = (string) $artifact->storage_disk;
            $path = (string) $artifact->storage_path;

            Storage::disk($disk)->delete($path);

            if (Storage::disk($disk)->exists($path)) {
                throw new RuntimeException("O artefato central [{$disk}:{$path}] não pôde ser removido.");
            }
        }
    }

    private function hasActiveStagingSubscriptions(): bool
    {
        if (Schema::hasTable('subscriptions')
            && DB::table('subscriptions')
                ->whereNull('ends_at')
                ->whereNotIn('stripe_status', ['canceled', 'incomplete_expired'])
                ->exists()) {
            return true;
        }

        return Schema::hasTable('tenant_addon_subscriptions')
            && DB::table('tenant_addon_subscriptions')->whereNull('canceled_at')->exists();
    }
}
