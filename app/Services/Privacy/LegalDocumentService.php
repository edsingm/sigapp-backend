<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Repositories\CentralLegalAcceptanceRepository;
use App\Repositories\Tenant\LegalAcceptanceRepository;
use Illuminate\Support\Str;

class LegalDocumentService
{
    public function __construct(
        private readonly CentralLegalAcceptanceRepository $centralAcceptances,
        private readonly LegalAcceptanceRepository $tenantAcceptances,
    ) {}

    /**
     * @return list<string>
     */
    public function documentKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_values(array_filter(
            (array) config('legal.document_keys', []),
            static fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        return $keys;
    }

    /**
     * @return list<string>
     */
    public function requiredAcceptanceKeys(): array
    {
        $required = [];

        foreach ($this->publishedDocuments() as $document) {
            if ($document['requires_acceptance']) {
                $required[] = $document['key'];
            }
        }

        return $required;
    }

    /**
     * @return array{documents: list<array<string, mixed>>, reacceptance_required: bool}
     */
    public function catalog(?int $tenantUserId = null): array
    {
        $latest = $tenantUserId === null
            ? []
            : $this->indexLatestTenantAcceptances($tenantUserId);

        $documents = [];
        $reacceptanceRequired = false;

        foreach ($this->publishedDocuments() as $document) {
            $acceptance = $latest[$document['key']] ?? null;
            $acceptedHash = is_array($acceptance) ? $acceptance['document_hash'] : '';
            $needsReacceptance = $tenantUserId !== null
                && $document['requires_acceptance']
                && $acceptedHash !== $document['hash'];

            if ($needsReacceptance) {
                $reacceptanceRequired = true;
            }

            $documents[] = [
                ...$document,
                'accepted_at' => is_array($acceptance) ? $acceptance['accepted_at'] : null,
                'accepted_version' => is_array($acceptance) ? $acceptance['document_version'] : null,
                'accepted_hash' => $acceptedHash !== '' ? $acceptedHash : null,
                'needs_reacceptance' => $needsReacceptance,
            ];
        }

        return [
            'documents' => $documents,
            'reacceptance_required' => $reacceptanceRequired,
        ];
    }

    /**
     * @param  list<string>|null  $documentKeys
     * @return list<string>
     */
    public function recordCentralAcceptances(
        string $tenantId,
        string $actorEmail,
        ?string $ipAddress,
        ?string $userAgent,
        ?array $documentKeys = null,
    ): array {
        $keys = $this->resolveKeys($documentKeys);
        $now = now();
        $email = Str::lower(trim($actorEmail));
        $ipHash = hash('sha256', $ipAddress ?? '');
        $agent = $this->truncateUserAgent($userAgent);

        foreach ($this->publishedDocuments() as $document) {
            if (! in_array($document['key'], $keys, true)) {
                continue;
            }

            $this->centralAcceptances->create([
                'tenant_id' => $tenantId,
                'actor_email' => $email,
                'document_key' => $document['key'],
                'document_version' => $document['version'],
                'document_hash' => $document['hash'],
                'accepted_at' => $now,
                'ip_hash' => $ipHash,
                'user_agent' => $agent,
            ]);
        }

        return $keys;
    }

    /**
     * @param  list<string>|null  $documentKeys
     * @return list<string>
     */
    public function recordTenantUserAcceptances(int $userId, ?array $documentKeys = null): array
    {
        $keys = $this->resolveKeys($documentKeys);
        $now = now();

        foreach ($this->publishedDocuments() as $document) {
            if (! in_array($document['key'], $keys, true)) {
                continue;
            }

            $this->tenantAcceptances->create([
                'user_id' => $userId,
                'document_key' => $document['key'],
                'document_version' => $document['version'],
                'document_hash' => $document['hash'],
                'accepted_at' => $now,
            ]);
        }

        return $keys;
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     version: string,
     *     effective_at: string,
     *     url: string,
     *     path: string,
     *     hash: string,
     *     requires_acceptance: bool
     * }>
     */
    public function publishedDocuments(): array
    {
        $documents = [];

        foreach ($this->documentKeys() as $key) {
            $configured = (array) config('legal.'.$key, []);
            $path = (string) ($configured['url'] ?? '');

            $documents[] = [
                'key' => (string) ($configured['key'] ?? $key),
                'title' => (string) ($configured['title'] ?? $key),
                'version' => (string) ($configured['version'] ?? ''),
                'effective_at' => (string) ($configured['effective_at'] ?? ''),
                'url' => $this->publicUrl($path),
                'path' => $path,
                'hash' => (string) ($configured['hash'] ?? ''),
                'requires_acceptance' => (bool) ($configured['requires_acceptance'] ?? false),
            ];
        }

        return $documents;
    }

    /**
     * @return array<string, array{document_key: string, document_version: string, document_hash: string, accepted_at: string|null}>
     */
    private function indexLatestTenantAcceptances(int $userId): array
    {
        $indexed = [];

        foreach ($this->tenantAcceptances->latestByUserId($userId) as $acceptance) {
            $key = $acceptance['document_key'];
            if (isset($indexed[$key])) {
                continue;
            }

            $acceptedAt = $acceptance['accepted_at'];

            $indexed[$key] = [
                'document_key' => $key,
                'document_version' => $acceptance['document_version'],
                'document_hash' => $acceptance['document_hash'],
                'accepted_at' => $acceptedAt instanceof \DateTimeInterface
                    ? $acceptedAt->format(\DateTimeInterface::ATOM)
                    : null,
            ];
        }

        return $indexed;
    }

    /**
     * @param  list<string>|null  $documentKeys
     * @return list<string>
     */
    private function resolveKeys(?array $documentKeys): array
    {
        if ($documentKeys === null || $documentKeys === []) {
            return $this->requiredAcceptanceKeys();
        }

        $known = $this->documentKeys();

        return array_values(array_filter(
            $documentKeys,
            static fn (string $key): bool => in_array($key, $known, true),
        ));
    }

    private function publicUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim((string) config('app.landing_url'), '/').$path;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return $userAgent;
        }

        return substr($userAgent, 0, 500);
    }
}
