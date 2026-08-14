<?php

declare(strict_types=1);

namespace Tests\Unit\Privacy;

use App\Casts\EncryptedWithTenantKey;
use App\Encryption\TenantEncrypter;
use App\Encryption\TenantKeyVault;
use App\Models\Central\Tenant;
use App\Services\Privacy\TenantLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_key_vault_wraps_and_reveals_with_app_key(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Crypto',
            'slug' => 'crypto-tenant',
            'status' => Tenant::STATUS_PENDING,
        ]);

        $vault = app(TenantKeyVault::class);
        $plaintext = base64_encode(random_bytes(32));
        $vault->wrapAndStore($tenant, $plaintext);

        $stored = (string) $tenant->fresh()?->getAttribute('encryption_key');
        $this->assertStringStartsWith('enc:v1:', $stored);
        $this->assertNotSame($plaintext, $stored);
        $fresh = $tenant->fresh();
        $this->assertInstanceOf(Tenant::class, $fresh);
        $this->assertSame($plaintext, $vault->reveal($fresh));
    }

    public function test_encrypted_with_tenant_key_round_trips_and_isolates_keys(): void
    {
        $encrypter = app(TenantEncrypter::class);
        $encrypter->configure(base64_encode(random_bytes(32)));
        $cast = new EncryptedWithTenantKey;

        $cipher = $cast->set(new Tenant, 'cpf_cnpj', '52998224725', []);
        $this->assertIsString($cipher);
        $this->assertNotSame('52998224725', $cipher);
        $this->assertSame('52998224725', $cast->get(new Tenant, 'cpf_cnpj', $cipher, []));

        $other = new TenantEncrypter;
        $other->configure(base64_encode(random_bytes(32)));
        $this->app->instance(TenantEncrypter::class, $other);
        $this->assertNotSame('52998224725', (new EncryptedWithTenantKey)->get(new Tenant, 'cpf_cnpj', $cipher, []));
    }

    public function test_cancel_schedules_wipe_without_deleting_billing_tax_id(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Cancelable',
            'slug' => 'cancelable',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $tenant->forceFill(['billing_tax_id' => '52998224725'])->save();

        $tenant->cancel();
        $tenant->refresh();

        $this->assertSame(Tenant::STATUS_CANCELLED, $tenant->getAttribute('status'));
        $this->assertNotNull($tenant->getAttribute('cancelled_at'));
        $this->assertNotNull($tenant->getAttribute('wipe_scheduled_at'));
        $this->assertSame('52998224725', $tenant->getAttribute('billing_tax_id'));
    }

    public function test_wipe_removes_s3_prefix_and_keeps_billing_identifiers(): void
    {
        Storage::fake('s3');

        $tenant = Tenant::query()->create([
            'name' => 'Wipe Me',
            'slug' => 'wipe-me',
            'status' => Tenant::STATUS_CANCELLED,
            'stripe_id' => 'cus_keep_me',
            'database_created' => false,
        ]);
        $tenant->forceFill([
            'billing_tax_id' => '52998224725',
            'encryption_key' => 'enc:v1:secret',
            'admin_email' => 'admin@example.com',
        ])->save();

        $prefix = 'tenants/'.$tenant->getKey().'/docs/contrato.pdf';
        Storage::disk('s3')->put($prefix, 'conteudo-sensivel');

        $wiped = app(TenantLifecycleService::class)->wipe($tenant, force: true);
        $wiped->refresh();

        $this->assertNotNull($wiped->getAttribute('wiped_at'));
        $this->assertSame('cus_keep_me', $wiped->getAttribute('stripe_id'));
        $this->assertSame('52998224725', $wiped->getAttribute('billing_tax_id'));
        $this->assertNull($wiped->getAttribute('encryption_key'));
        $this->assertSame('anonimizado', $wiped->getAttribute('admin_name'));
        $this->assertFalse(Storage::disk('s3')->exists($prefix));
    }

    public function test_wipe_is_a_noop_when_auto_wipe_is_disabled_and_not_forced(): void
    {
        config(['privacy.auto_wipe_enabled' => false]);

        $tenant = Tenant::query()->create([
            'name' => 'Hold',
            'slug' => 'hold-wipe',
            'status' => Tenant::STATUS_CANCELLED,
            'database_created' => false,
        ]);

        $result = app(TenantLifecycleService::class)->wipe($tenant, force: false);

        $this->assertNull($result->getAttribute('wiped_at'));
    }
}
