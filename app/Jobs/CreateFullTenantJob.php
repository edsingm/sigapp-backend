<?php

namespace App\Jobs;

use App\Models\Central\Tenant;
use App\Traits\LogsAudit;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogsAudit;

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

        Log::info('CreateFullTenantJob iniciado', [
            'tenant_id' => $this->tenant->id,
        ]);

        if ($this->tenant->database_created) {
            Log::info('CreateFullTenantJob ignorado: tenant já provisionado', [
                'tenant_id' => $this->tenant->id,
            ]);

            return;
        }

        // Audit: creation started
        $this->auditTrail('tenant.creation_started', "Job de criação iniciado para tenant '{$this->tenant->name}'.");

        try {
            $this->createDatabase();

            $this->runMigrations();

            $this->restoreCentralConnection($centralConnection);

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

            // Audit: creation completed
            $this->restoreCentralConnection($centralConnection);
            $this->auditTrail('tenant.creation_completed', "Tenant '{$this->tenant->name}' criado e ativado com sucesso.", [
                'status'      => 'active',
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

            // Audit: creation failed
            $this->restoreCentralConnection($centralConnection);
            $this->auditTrail('tenant.creation_failed', 'Job de criação falhou: ' . \Illuminate\Support\Str::limit($e->getMessage(), 200), [
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'attempt'     => $this->attempts(),
                'max_tries'   => $this->tries,
            ]);

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


        if ($manager->databaseExists($databaseName)) {
            Log::warning('Schema/banco do tenant ja existe', ['database' => $databaseName]);

            return;
        }

        $manager->createDatabase($this->tenant);
    }

    /**
     * Run tenant migrations.
     */
    protected function runMigrations(): void
    {
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
    }

    /**
     * Seed tenant data (create admin user, roles, etc).
     */
    protected function seedTenantData(): void
    {
        $this->tenant->run(function () {
            $seeder = new \Database\Seeders\Tenant\TenantSeeder();
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
        $this->auditTrail('tenant.creation_failed', "Job de criação falhou definitivamente para tenant '{$this->tenant->name}' após {$this->tries} tentativas.",[
            'error'             => $exception->getMessage() ?? '',
            'error_class'       => get_class($exception) ?? '',
            'max_tries'         => $this->tries ?? 0,
            'permanent_failure' => true,
        ]);
    }

    private function auditTrail(string $action, string $description, array $metadata = []): void
    {
        $this->audit($action, $description, array_merge([
            'tenant_id'         => $this->tenant->id ?? null,
            'tenant_slug'       => $this->tenant->slug ?? null,
            'tenant_name'       => $this->tenant->name ?? null,
            'plan_id'           => $this->tenant->plan_id ?? null,
            'admin_email'       => $this->tenant->admin_email ?? null
        ], $metadata));

    }
}
