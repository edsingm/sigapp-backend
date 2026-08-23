<?php

declare(strict_types=1);

namespace App\Encryption;

use App\Exceptions\TenantEncryptionException;
use App\Models\Central\Tenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TenantKeyVault
{
    public function reveal(Tenant $tenant): string
    {
        $stored = (string) $tenant->getAttribute('encryption_key');
        if ($stored === '') {
            throw new TenantEncryptionException('O tenant não possui chave de criptografia.');
        }

        if (str_starts_with($stored, 'enc:v1:')) {
            try {
                return Crypt::decryptString(substr($stored, 7));
            } catch (DecryptException $exception) {
                throw new TenantEncryptionException(
                    'Não foi possível abrir a chave de criptografia do tenant.',
                    'TENANT_ENCRYPTION_KEY_UNWRAP_FAILED',
                    $exception,
                );
            }
        }

        return $stored;
    }

    public function wrapAndStore(Tenant $tenant, string $plaintextKey): void
    {
        $driver = (string) config('privacy.tenant_kek_driver', 'local');
        if (! in_array($driver, ['local', 'kms'], true)) {
            throw new TenantEncryptionException(
                'O driver de envelopamento da chave do tenant é inválido.',
                'TENANT_ENCRYPTION_KEY_INVALID',
            );
        }

        $stored = 'enc:v1:'.Crypt::encryptString($plaintextKey);

        $tenant->update(['encryption_key' => $stored]);
    }

    public function ensure(Tenant $tenant): string
    {
        $connection = $tenant->getConnectionName();

        return DB::connection($connection)->transaction(function () use ($tenant, $connection): string {
            /** @var Tenant $locked */
            $locked = Tenant::on($connection)->lockForUpdate()->findOrFail($tenant->getKey());
            $stored = $locked->getAttribute('encryption_key');

            if (is_string($stored) && $stored !== '') {
                $plaintext = $this->reveal($locked);
                app(TenantEncrypter::class)->configure($plaintext);
                $tenant->setAttribute('encryption_key', $stored);

                return $plaintext;
            }

            $plaintext = base64_encode(random_bytes(32));
            $this->wrapAndStore($locked, $plaintext);
            app(TenantEncrypter::class)->configure($plaintext);
            $tenant->setAttribute('encryption_key', $locked->fresh()?->getAttribute('encryption_key'));

            return $plaintext;
        });
    }
}
