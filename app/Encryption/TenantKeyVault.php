<?php

declare(strict_types=1);

namespace App\Encryption;

use App\Models\Central\Tenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class TenantKeyVault
{
    public function reveal(Tenant $tenant): string
    {
        $stored = (string) $tenant->getAttribute('encryption_key');
        if ($stored === '') {
            throw new RuntimeException('Tenant sem encryption_key.');
        }

        if (str_starts_with($stored, 'enc:v1:')) {
            try {
                return Crypt::decryptString(substr($stored, 7));
            } catch (DecryptException $exception) {
                throw new RuntimeException('Falha ao abrir a chave do tenant.', 0, $exception);
            }
        }

        return $stored;
    }

    public function wrapAndStore(Tenant $tenant, string $plaintextKey): void
    {
        $driver = (string) config('privacy.tenant_kek_driver', 'local');
        $stored = $driver === 'kms' || $driver === 'local'
            ? 'enc:v1:'.Crypt::encryptString($plaintextKey)
            : $plaintextKey;

        $tenant->update(['encryption_key' => $stored]);
    }
}
