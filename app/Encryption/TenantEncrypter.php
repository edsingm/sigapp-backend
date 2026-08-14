<?php

declare(strict_types=1);

namespace App\Encryption;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

class TenantEncrypter
{
    private ?Encrypter $encrypter = null;

    public function encrypt(mixed $value): string
    {
        return $this->driver()->encrypt($value, false);
    }

    public function decrypt(string $payload): mixed
    {
        return $this->driver()->decrypt($payload, false);
    }

    public function configure(string $rawKey): void
    {
        $binary = $this->normalizeKey($rawKey);
        $this->encrypter = new Encrypter($binary, 'AES-256-CBC');
    }

    public function forget(): void
    {
        $this->encrypter = null;
    }

    public function isConfigured(): bool
    {
        return $this->encrypter instanceof Encrypter;
    }

    private function driver(): Encrypter
    {
        if (! $this->encrypter instanceof Encrypter) {
            throw new RuntimeException('TenantEncrypter não configurado para o tenant atual.');
        }

        return $this->encrypter;
    }

    private function normalizeKey(string $rawKey): string
    {
        if (str_starts_with($rawKey, 'base64:')) {
            $decoded = base64_decode(substr($rawKey, 7), true);
            if (is_string($decoded) && strlen($decoded) === 32) {
                return $decoded;
            }
        }

        $decoded = base64_decode($rawKey, true);
        if (is_string($decoded) && strlen($decoded) === 32) {
            return $decoded;
        }

        if (strlen($rawKey) === 32) {
            return $rawKey;
        }

        return hash('sha256', $rawKey, true);
    }
}
