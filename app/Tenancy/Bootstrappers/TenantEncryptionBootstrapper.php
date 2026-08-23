<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Encryption\TenantEncrypter;
use App\Encryption\TenantKeyVault;
use App\Exceptions\TenantEncryptionException;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

class TenantEncryptionBootstrapper implements TenancyBootstrapper
{
    public function __construct(
        private readonly TenantEncrypter $encrypter,
        private readonly TenantKeyVault $vault,
    ) {}

    public function bootstrap(TenantContract $tenant): void
    {
        if (! $tenant instanceof Tenant) {
            return;
        }

        $stored = $tenant->getAttribute('encryption_key');
        if (! is_string($stored) || $stored === '') {
            $this->encrypter->forget();
            Log::critical('Tenant sem chave de criptografia; PII ficará indisponível.', [
                'tenant_id' => $tenant->getKey(),
                'error_code' => 'TENANT_ENCRYPTION_KEY_MISSING',
            ]);

            return;
        }

        try {
            $this->encrypter->configure($this->vault->reveal($tenant));
        } catch (TenantEncryptionException $exception) {
            $this->encrypter->forget();
            Log::critical('Chave de criptografia do tenant indisponível; PII ficará bloqueada.', [
                'tenant_id' => $tenant->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    public function revert(): void
    {
        $this->encrypter->forget();
    }
}
