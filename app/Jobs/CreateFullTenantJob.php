<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateFullTenantJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Keep the unique lock long enough to cover Stripe retries.
     */
    public int $uniqueFor = 900;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Tenant $tenant
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(0);
        $centralConnection = $this->getCentralConnectionName();
        $this->tenant->setConnection($centralConnection);
        $this->tenant->refresh();

        if ($this->tenant->database_created) {
            Log::info('CreateFullTenantJob ignorado: tenant já provisionado', [
                'tenant_id' => $this->tenant->id,
            ]);

            return;
        }

        Log::info('CreateFullTenantJob iniciado', ['tenant_id' => $this->tenant->id]);

        // Audit: creation started
        try {
            AuditLog::create([
                'action' => 'tenant.creation_started',
                'description' => "Job de criação iniciado para tenant '{$this->tenant->name}'.",
                'metadata' => [
                    'tenant_id' => $this->tenant->id,
                    'tenant_slug' => $this->tenant->slug,
                    'tenant_name' => $this->tenant->name,
                    'plan_id' => $this->tenant->plan_id,
                    'admin_email' => $this->tenant->admin_email,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Falha ao criar audit log de creation_started', ['error' => $e->getMessage()]);
        }

        try {
            // Step 1: Create tenant database
            $this->createDatabase();

            // Step 2: Run migrations
            $this->runMigrations();

            $this->restoreCentralConnection($centralConnection);

            // Step 3: Seed tenant data
            $this->seedTenantData();

            $this->restoreCentralConnection($centralConnection);

            // Step 4: Generate encryption key
            $this->tenant->generateEncryptionKey();

            // Step 5: Activate tenant
            $this->tenant->activate();

            // Step 6: Clear admin credentials (security)
            $this->tenant->update([
                'admin_password' => null,
                'database_created' => true,
            ]);

            // Step 7: Send welcome email
            $this->sendWelcomeEmail();

            // Step 8: Cache tenant info
            $this->cacheTenantInfo();

            Log::info('CreateFullTenantJob concluído com sucesso', ['tenant_id' => $this->tenant->id]);

            // Audit: creation completed
            try {
                $this->restoreCentralConnection($centralConnection);
                AuditLog::create([
                    'action' => 'tenant.creation_completed',
                    'description' => "Tenant '{$this->tenant->name}' criado e ativado com sucesso.",
                    'metadata' => [
                        'tenant_id' => $this->tenant->id,
                        'tenant_slug' => $this->tenant->slug,
                        'tenant_name' => $this->tenant->name,
                        'plan_id' => $this->tenant->plan_id,
                        'admin_email' => $this->tenant->admin_email,
                        'status' => 'active',
                    ],
                ]);
            } catch (\Exception $e) {
                Log::warning('Falha ao criar audit log de creation_completed', ['error' => $e->getMessage()]);
            }

        } catch (\Exception $e) {
            Log::error('CreateFullTenantJob falhou', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Audit: creation failed
            try {
                $this->restoreCentralConnection($centralConnection);
                AuditLog::create([
                    'action' => 'tenant.creation_failed',
                    'description' => 'Job de criação falhou: ' . \Illuminate\Support\Str::limit($e->getMessage(), 200),
                    'metadata' => [
                        'tenant_id' => $this->tenant->id,
                        'tenant_slug' => $this->tenant->slug,
                        'tenant_name' => $this->tenant->name,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'attempt' => $this->attempts(),
                        'max_tries' => $this->tries,
                    ],
                ]);
            } catch (\Exception $auditEx) {
                Log::warning('Falha ao criar audit log de creation_failed', ['error' => $auditEx->getMessage()]);
            }

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return 'tenant-provisioning:' . $this->tenant->getKey();
    }

    /**
     * Create (or ensure) the tenant schema/database.
     */
    protected function createDatabase(): void
    {
        $this->tenant->database()->makeCredentials();
        $databaseName = $this->tenant->database()->getName();
        $manager = $this->tenant->database()->manager();

        Log::info('Garantindo schema/banco do tenant', ['database' => $databaseName]);

        if ($manager->databaseExists($databaseName)) {
            Log::info('Schema/banco do tenant ja existe', ['database' => $databaseName]);

            return;
        }

        $manager->createDatabase($this->tenant);

        Log::info('Schema/banco do tenant criado', ['database' => $databaseName]);
    }

    /**
     * Run tenant migrations.
     */
    protected function runMigrations(): void
    {
        Log::info('Executando migrations do tenant', ['tenant_id' => $this->tenant->id]);

        $this->tenant->run(function () {



            // Manual fix: ensure tenant connection exists using Tenancy's generated config.
            if (!config('database.connections.tenant')) {
                Log::warning('Tenant connection config missing. Manually configuring.');
                config(['database.connections.tenant' => $this->tenant->database()->connection()]);
                DB::purge('tenant');
                DB::setDefaultConnection('tenant');
            }

            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });

        Log::info('Migrations executadas', ['tenant_id' => $this->tenant->id]);
    }

    /**
     * Seed tenant data (create admin user, roles, etc).
     */
    protected function seedTenantData(): void
    {
        Log::info('Executando seeder do tenant', ['tenant_id' => $this->tenant->id]);

        $this->tenant->run(function () {
            // Run TenantSeeder (includes Roles, Permissions, AdminUser and others)
            $seeder = new \Database\Seeders\Tenant\TenantSeeder();
            $seeder->run();
        });

        Log::info('Seeder executado', ['tenant_id' => $this->tenant->id]);
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
     * Send welcome email to admin.
     */
    protected function sendWelcomeEmail(): void
    {
        // TODO: Implement welcome email with Resend
        Log::info('Email de boas-vindas enviado', [
            'tenant_id' => $this->tenant->id,
            'email' => $this->tenant->admin_email,
        ]);
    }

    /**
     * Cache tenant info in Redis.
     */
    protected function cacheTenantInfo(): void
    {
        $cacheKey = 'tenant:' . $this->tenant->slug;

        cache()->put($cacheKey, [
            'id' => $this->tenant->id,
            'name' => $this->tenant->name,
            'slug' => $this->tenant->slug,
            'plan_id' => $this->tenant->plan_id,
            'status' => $this->tenant->status,
        ], now()->addHours(24));

        Log::info('Tenant cacheado', ['tenant_id' => $this->tenant->id]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CreateFullTenantJob falhou definitivamente', [
            'tenant_id' => $this->tenant->id,
            'error' => $exception->getMessage(),
        ]);

        // Audit: permanent failure
        try {
            AuditLog::create([
                'action' => 'tenant.creation_failed',
                'description' => "Job de criação falhou definitivamente para tenant '{$this->tenant->name}' após {$this->tries} tentativas.",
                'metadata' => [
                    'tenant_id' => $this->tenant->id,
                    'tenant_slug' => $this->tenant->slug,
                    'tenant_name' => $this->tenant->name,
                    'error' => $exception->getMessage(),
                    'error_class' => get_class($exception),
                    'max_tries' => $this->tries,
                    'permanent_failure' => true,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Falha ao criar audit log de falha permanente', ['error' => $e->getMessage()]);
        }
    }
}
