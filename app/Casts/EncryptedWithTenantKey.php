<?php

declare(strict_types=1);

namespace App\Casts;

use App\Encryption\TenantEncrypter;
use App\Exceptions\TenantEncryptionException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedWithTenantKey implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $encrypter = app(TenantEncrypter::class);
        $decrypted = $encrypter->decrypt($value);

        if (! is_string($decrypted)) {
            throw new TenantEncryptionException(
                'O conteúdo descriptografado não possui formato válido.',
                'TENANT_PII_PAYLOAD_INVALID',
            );
        }

        return $decrypted;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return app(TenantEncrypter::class)->encrypt((string) $value);
    }
}
