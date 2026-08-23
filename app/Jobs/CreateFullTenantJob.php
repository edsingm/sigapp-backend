<?php

namespace App\Jobs;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\User;
use App\Notifications\TenantWelcomeNotification;
use App\Traits\LogsAudit;
use Database\Seeders\Tenant\TenantSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Tries(3)]
#[Backoff(60)]
#[Timeout(600)]
#[Queue('tenant-provisioning')]
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

            // A chave precisa existir antes do primeiro tenant->run(), pois migrations,
            // seeders e observers já podem acessar models com PII cifrada.
            $this->tenant->ensureEncryptionKey();

            $this->runMigrations();

            $this->restoreCentralConnection($centralConnection);

            $this->seedTenantData();

            $this->restoreCentralConnection($centralConnection);

            $this->verifyProvisioningPostconditions();

            // A ativação é a última mutação crítica do provisionamento.
            $this->tenant->update([
                'status' => TenantStatus::ACTIVE->value,
                'admin_password' => null,
                'database_created' => true,
                'setup_completed_at' => now(),
            ]);

            // E-mail e cache são pós-ativação e não podem reverter um setup válido.
            try {
                $this->sendWelcomeEmail();
                $this->cacheTenantInfo();
            } catch (\Throwable $exception) {
                Log::warning('Pós-processamento do provisionamento falhou', [
                    'tenant_id' => $this->tenant->id,
                    'exception' => $exception::class,
                ]);
            }

            // Auditoria: criação concluída
            $this->restoreCentralConnection($centralConnection);
            $this->auditTrail('tenant.creation_completed', "Tenant '{$this->tenantName()}' criado e ativado com sucesso.", [
                'status' => TenantStatus::ACTIVE->value,
            ]);

            Log::info('CreateFullTenantJob concluído com sucesso', [
                'tenant_id' => $this->tenant->id,
            ]);

        } catch (\Throwable $e) {
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

            $exitCode = Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException("Tenant migrations falharam com exit code {$exitCode}.");
            }
        });
    }

    /**
     * Semeia os dados do tenant (cria usuário admin, cargos, etc).
     */
    protected function seedTenantData(): void
    {
        $this->tenant->run(function () {
            $exitCode = Artisan::call('db:seed', [
                '--class' => TenantSeeder::class,
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException("Tenant seed falhou com exit code {$exitCode}.");
            }
        });
    }

    protected function verifyProvisioningPostconditions(): void
    {
        $this->tenant->run(function (): void {
            foreach (['migrations', 'users', 'roles', 'permissions', 'corretores_externos'] as $table) {
                if (! Schema::connection('tenant')->hasTable($table)) {
                    throw new \RuntimeException("Pós-condição de provisionamento ausente: tabela {$table}.");
                }
            }

            $adminEmail = $this->tenantAdminEmail();
            $admin = is_string($adminEmail)
                ? User::query()->where('email', $adminEmail)->first()
                : null;

            if (! $admin instanceof User) {
                throw new \RuntimeException('Pós-condição de provisionamento ausente: usuário administrador.');
            }

            if (! DB::connection('tenant')->table('roles')->exists()) {
                throw new \RuntimeException('Pós-condição de provisionamento ausente: roles.');
            }

            $directoryReady = tenancy()->central(fn (): bool => TenantUserDirectory::query()
                ->where('tenant_id', (string) $this->tenant->getKey())
                ->where('tenant_user_id', (string) $admin->getKey())
                ->where('email_normalized', mb_strtolower(trim((string) $admin->getAttribute('email'))))
                ->where('active', true)
                ->exists());

            if (! $directoryReady) {
                throw new \RuntimeException('Pós-condição de provisionamento ausente: diretório central do administrador.');
            }

            $probe = 'pii-probe-'.Str::uuid().'@invalid.test';
            DB::connection('tenant')->beginTransaction();

            try {
                $model = CorretorExterno::query()->create([
                    'nome' => 'PII provisioning probe',
                    'email' => $probe,
                    'telefone' => '00000000000',
                    'creci' => 'probe-'.Str::uuid(),
                ]);
                $raw = DB::connection('tenant')
                    ->table('corretores_externos')
                    ->where('id', $model->getKey())
                    ->value('email');

                if (! is_string($raw) || $raw === $probe) {
                    throw new \RuntimeException('Round-trip de PII persistiu plaintext.');
                }

                $fresh = $model->fresh();
                if (! $fresh instanceof CorretorExterno || $fresh->getAttribute('email') !== $probe) {
                    throw new \RuntimeException('Round-trip de PII não recuperou o valor original.');
                }
            } finally {
                DB::connection('tenant')->rollBack();
            }
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
