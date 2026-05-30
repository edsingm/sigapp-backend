<?php

namespace App\Jobs;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Notifications\TenantWelcomeNotification;
use App\Traits\LogsAudit;
use Database\Seeders\Tenant\TenantSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Tries(3)]
#[Backoff(60)]
class CreateFullTenantJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, LogsAudit, Queueable, SerializesModels;

    /**
     * Mantém o bloqueio exclusivo por tempo suficiente para cobrir as tentativas do Stripe.
     */
    public int $uniqueFor = 900;

    /**
     * Cria uma nova instância do job.
     */
    public function __construct(
        public Tenant $tenant
    ) {}

    /**
     * Executa o job.
     */
    public function handle(): void
    {
        set_time_limit(0);
        $centralConnection = $this->getCentralConnectionName();
        $this->tenant->setConnection($centralConnection);
        $this->tenant->refresh();

        Log::info('CreateFullTenantJob iniciado', [
            'tenant_id' => $this->tenant->id,
        ]);

        if ((bool) $this->tenant->getAttribute('database_created')) {
            Log::info('CreateFullTenantJob ignorado: tenant já provisionado', [
                'tenant_id' => $this->tenant->id,
            ]);

            return;
        }

        // Auditoria: criação iniciada
        $this->auditTrail('tenant.creation_started', "Job de criação iniciado para tenant '{$this->tenantName()}'.");

        try {
            $this->createDatabase();

            $this->runMigrations();

            $this->restoreCentralConnection($centralConnection);

            $this->seedTenantData();

            $this->restoreCentralConnection($centralConnection);

            // Passo 4: Gera a chave de criptografia
            $this->tenant->generateEncryptionKey();

            // Passo 5: Ativa o tenant
            $this->tenant->activate();

            // Passo 6: Limpa as credenciais de admin (segurança)
            $this->tenant->update([
                'admin_password' => null,
                'database_created' => true,
            ]);

            // Passo 7: Envia o e-mail de boas-vindas
            $this->sendWelcomeEmail();

            // Passo 8: Armazena informações do tenant em cache
            $this->cacheTenantInfo();

            // Auditoria: criação concluída
            $this->restoreCentralConnection($centralConnection);
            $this->auditTrail('tenant.creation_completed', "Tenant '{$this->tenantName()}' criado e ativado com sucesso.", [
                'status' => TenantStatus::ACTIVE->value,
            ]);

            Log::info('CreateFullTenantJob concluído com sucesso', [
                'tenant_id' => $this->tenant->id,
            ]);

        } catch (\Exception $e) {
            Log::error('CreateFullTenantJob falhou', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Auditoria: criação falhou
            $this->restoreCentralConnection($centralConnection);
            $this->auditTrail('tenant.creation_failed', 'Job de criação falhou: '.Str::limit($e->getMessage(), 200), [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'attempt' => $this->attempts(),
                'max_tries' => $this->maxTries(),
            ]);

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return 'tenant-provisioning:'.$this->tenant->getKey();
    }

    /**
     * Cria (ou garante) o esquema/banco de dados do tenant.
     */
    protected function createDatabase(): void
    {
        $this->tenant->database()->makeCredentials();
        $databaseName = $this->tenant->database()->getName();
        $manager = $this->tenant->database()->manager();

        if ($manager->databaseExists($databaseName)) {
            Log::warning('Schema/banco do tenant ja existe', ['database' => $databaseName]);

            return;
        }

        $manager->createDatabase($this->tenant);
    }

    /**
     * Executa as migrações do tenant.
     */
    protected function runMigrations(): void
    {
        $this->tenant->run(function () {
            // Correção manual: garante que a conexão do tenant exista usando a configuração gerada pelo Tenancy.
            if (! config('database.connections.tenant')) {
                Log::warning('Configuração de conexão do tenant ausente. Configurando manualmente.');
                config(['database.connections.tenant' => $this->tenant->database()->connection()]);
                DB::purge('tenant');
                DB::setDefaultConnection('tenant');
            }

            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });
    }

    /**
     * Semeia os dados do tenant (cria usuário admin, cargos, etc).
     */
    protected function seedTenantData(): void
    {
        $this->tenant->run(function () {
            $seeder = new TenantSeeder;
            $seeder->run();
        });
    }

    protected function getCentralConnectionName(): string
    {
        $configured = config('tenancy.database.central_connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return DB::getDefaultConnection();
    }

    protected function restoreCentralConnection(string $connection): void
    {
        DB::setDefaultConnection($connection);
        $this->tenant->setConnection($connection);
    }

    /**
     * Envia e-mail de boas-vindas para o admin.
     */
    protected function sendWelcomeEmail(): void
    {
        $adminEmail = $this->tenantAdminEmail();

        if ($adminEmail === null) {
            Log::warning('Email de boas-vindas não enviado: admin_email ausente', [
                'tenant_id' => $this->tenant->id,
            ]);

            return;
        }

        $notification = new TenantWelcomeNotification(
            tenantName: $this->tenantName(),
            appUrl: config('app.frontend_url', config('app.url')),
        );

        $this->tenant->notify($notification);

        Log::info('Email de boas-vindas enviado', [
            'tenant_id' => $this->tenant->id,
            'email' => $adminEmail,
        ]);
    }

    /**
     * Armazena informações do tenant em cache no Redis.
     */
    protected function cacheTenantInfo(): void
    {
        $cacheKey = 'tenant:'.$this->tenantSlug();

        cache()->put($cacheKey, [
            'id' => $this->tenant->id,
            'name' => $this->tenantName(),
            'slug' => $this->tenantSlug(),
            'plan_id' => $this->tenant->getAttribute('plan_id'),
            'status' => $this->tenant->getAttribute('status'),
        ], now()->addHours(24));
    }

    /**
     * Lida com a falha do job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CreateFullTenantJob falhou definitivamente', [
            'tenant_id' => $this->tenant->id,
            'error' => $exception->getMessage(),
        ]);

        // Marca o tenant como falhado para que o frontend saiba e o cleanup não tente novamente
        try {
            $this->tenant->setConnection($this->getCentralConnectionName());
            $this->tenant->update(['status' => Tenant::STATUS_SETUP_FAILED]);
        } catch (\Throwable $e) {
            Log::error('Falha ao atualizar status do tenant para setup_failed', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Auditoria: falha permanente
        $tries = (new \ReflectionClass($this))->getAttributes(Tries::class)[0]->getArguments()[0] ?? 0;
        $this->auditTrail('tenant.creation_failed', "Job de criação falhou definitivamente para tenant '{$this->tenantName()}' após {$tries} tentativas.", [
            'error' => $exception->getMessage() ?? '',
            'error_class' => get_class($exception) ?? '',
            'max_tries' => $tries,
            'permanent_failure' => true,
        ]);
    }

    private function auditTrail(string $action, string $description, array $metadata = []): void
    {
        $this->audit($action, $description, array_merge([
            'tenant_id' => $this->tenant->id ?? null,
            'tenant_slug' => $this->tenantSlug(),
            'tenant_name' => $this->tenantName(),
            'plan_id' => $this->tenant->getAttribute('plan_id'),
            'admin_email' => $this->tenantAdminEmail(),
        ], $metadata));

    }

    private function tenantName(): string
    {
        return (string) $this->tenant->getAttribute('name');
    }

    private function tenantSlug(): string
    {
        return (string) $this->tenant->getAttribute('slug');
    }

    private function tenantAdminEmail(): ?string
    {
        $email = $this->tenant->getAttribute('admin_email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function maxTries(): int
    {
        $attributes = (new \ReflectionClass($this))->getAttributes(Tries::class);

        if ($attributes === []) {
            return 0;
        }

        $tries = $attributes[0]->getArguments()[0] ?? 0;

        return is_int($tries) ? $tries : 0;
    }
}
