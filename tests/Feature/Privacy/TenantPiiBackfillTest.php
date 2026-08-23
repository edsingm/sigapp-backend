<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Encryption\TenantEncrypter;
use App\Encryption\TenantPiiBlindIndexer;
use App\Jobs\BackfillTenantPiiEncryptionJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\CorretorExterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantPiiBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_encrypts_plaintext_and_marks_tenant_only_after_full_scan(): void
    {
        config(['database.connections.tenant_template' => config('database.connections.sqlite')]);

        $tenant = Tenant::query()->create([
            'name' => 'Legacy PII',
            'slug' => 'legacy-pii',
            'status' => Tenant::STATUS_ACTIVE,
            'database_created' => true,
            'pii_encryption_status' => 'completed',
            'pii_encryption_version' => 1,
        ]);
        $tenant->ensureEncryptionKey();
        $manager = $tenant->database()->manager();
        $databaseName = (string) $tenant->database()->getName();
        $manager->createDatabase($tenant);

        try {
            tenancy()->initialize($tenant);
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]));

            DB::connection('tenant')->table('corretores_externos')->insert([
                'nome' => 'Corretor legado',
                'email' => 'legado@example.test',
                'telefone' => '11999999999',
                'creci' => 'LEGACY-1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            tenancy()->end();

            (new BackfillTenantPiiEncryptionJob((string) $tenant->getKey(), 1))
                ->handle(app(TenantEncrypter::class), app(TenantPiiBlindIndexer::class));

            $tenant->refresh();
            $this->assertSame('completed', $tenant->getAttribute('pii_encryption_status'));
            $this->assertSame(BackfillTenantPiiEncryptionJob::VERSION, $tenant->getAttribute('pii_encryption_version'));
            $this->assertNotNull($tenant->getAttribute('pii_encrypted_at'));

            tenancy()->initialize($tenant);
            $raw = DB::connection('tenant')->table('corretores_externos')->value('email');
            $this->assertIsString($raw);
            $this->assertStringStartsWith(TenantEncrypter::PAYLOAD_PREFIX, $raw);
            $this->assertNotSame('legado@example.test', $raw);
            $this->assertSame(64, strlen((string) DB::connection('tenant')->table('corretores_externos')->value('email_hash')));
            $this->assertSame(64, strlen((string) DB::connection('tenant')->table('corretores_externos')->value('telefone_hash')));
            $this->assertSame('legado@example.test', CorretorExterno::query()->first()?->getAttribute('email'));
        } finally {
            tenancy()->end();

            if ($manager->databaseExists($databaseName)) {
                $manager->deleteDatabase($tenant);
            }
        }
    }
}
