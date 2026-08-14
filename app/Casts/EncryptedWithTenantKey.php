<?php

declare(strict_types=1);

namespace App\Casts;

use App\Encryption\TenantEncrypter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Throwable;

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
        if (! $encrypter->isConfigured()) {
            return $value;
        }

        try {
            $decrypted = $encrypter->decrypt($value);

            return is_string($decrypted) ? $decrypted : $value;
        } catch (Throwable) {
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $encrypter = app(TenantEncrypter::class);
        if (! $encrypter->isConfigured()) {
            return is_string($value) ? $value : null;
        }

        return $encrypter->encrypt((string) $value);
    }
}
