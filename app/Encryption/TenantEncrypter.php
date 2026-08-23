<?php

declare(strict_types=1);

namespace App\Encryption;

use App\Exceptions\TenantEncryptionException;
use Illuminate\Encryption\Encrypter;
use Throwable;

class TenantEncrypter
{
    public const PAYLOAD_PREFIX = 'tenant:v1:';

    private ?Encrypter $encrypter = null;

    private ?string $key = null;

    public function encrypt(mixed $value): string
    {
        return self::PAYLOAD_PREFIX.$this->driver()->encrypt($value, false);
    }

    public function decrypt(string $payload): mixed
    {
        $encrypted = str_starts_with($payload, self::PAYLOAD_PREFIX)
            ? substr($payload, strlen(self::PAYLOAD_PREFIX))
            : $payload;

        try {
            return $this->driver()->decrypt($encrypted, false);
        } catch (TenantEncryptionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TenantEncryptionException(
                'Não foi possível descriptografar os dados protegidos do tenant.',
                'TENANT_PII_DECRYPTION_FAILED',
                $exception,
            );
        }
    }

    public function blindIndex(string $value, string $context): string
    {
        $this->driver();

        if (! is_string($this->key)) {
            throw new TenantEncryptionException('A chave de criptografia do tenant não está disponível.');
        }

        return hash_hmac('sha256', $context."\0".$value, $this->key);
    }

    public function configure(string $rawKey): void
    {
        $binary = $this->normalizeKey($rawKey);
        $this->key = $binary;
        $this->encrypter = new Encrypter($binary, 'AES-256-CBC');
    }

    public function forget(): void
    {
        $this->key = null;
        $this->encrypter = null;
    }

    public function isConfigured(): bool
    {
        return $this->encrypter instanceof Encrypter;
    }

    public function looksLikeEncryptedPayload(string $payload): bool
    {
        $encoded = str_starts_with($payload, self::PAYLOAD_PREFIX)
            ? substr($payload, strlen(self::PAYLOAD_PREFIX))
            : $payload;
        $decoded = base64_decode($encoded, true);

        if (! is_string($decoded)) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json)
            && isset($json['iv'], $json['value'], $json['mac']);
    }

    private function driver(): Encrypter
    {
        if (! $this->encrypter instanceof Encrypter) {
            throw new TenantEncryptionException('A chave de criptografia do tenant não está disponível.');
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

        throw new TenantEncryptionException(
            'A chave de criptografia do tenant é inválida.',
            'TENANT_ENCRYPTION_KEY_INVALID',
        );
    }
}
