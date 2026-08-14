<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Encryption\TenantEncrypter;
use App\Encryption\TenantKeyVault;
use App\Models\Central\Tenant;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Throwable;

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
            $plaintext = base64_encode(random_bytes(32));
            $this->vault->wrapAndStore($tenant, $plaintext);
            $this->encrypter->configure($plaintext);

            return;
        }

        try {
            $this->encrypter->configure($this->vault->reveal($tenant));
        } catch (Throwable) {
            $this->encrypter->forget();
        }
    }

    public function revert(): void
    {
        $this->encrypter->forget();
    }
}
