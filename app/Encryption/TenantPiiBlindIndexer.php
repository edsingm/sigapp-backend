<?php

declare(strict_types=1);

namespace App\Encryption;

final readonly class TenantPiiBlindIndexer
{
    public function __construct(private TenantEncrypter $encrypter) {}

    public function email(string $email): string
    {
        return $this->encrypter->blindIndex(
            $this->normalizeEmail($email),
            'corretores_externos.email',
        );
    }

    public function phone(string $phone): string
    {
        return $this->encrypter->blindIndex(
            $this->normalizePhone($phone),
            'corretores_externos.telefone',
        );
    }

    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public function matchesStoredEmail(string $email, string $stored): bool
    {
        $plain = $this->encrypter->looksLikeEncryptedPayload($stored)
            ? $this->encrypter->decrypt($stored)
            : $stored;

        return is_string($plain) && hash_equals($this->email($email), $this->email($plain));
    }
}
