<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\DemoRequestReceived;
use App\Models\Central\DemoRequest;
use App\Repositories\DemoRequestRepository;

class DemoRequestService
{
    public function __construct(
        private readonly DemoRequestRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?string $ipAddress, ?string $userAgent): DemoRequest
    {
        $privacy = (array) config('legal.privacy_policy', []);

        $demoRequest = $this->repository->create([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'company' => (string) $data['company'],
            'city' => isset($data['city']) ? (string) $data['city'] : null,
            'role' => isset($data['role']) ? (string) $data['role'] : null,
            'land_context' => isset($data['land_context']) ? (string) $data['land_context'] : null,
            'source' => isset($data['source']) && $data['source'] !== '' ? (string) $data['source'] : 'site',
            'page' => isset($data['page']) ? (string) $data['page'] : null,
            'ip_hash' => hash('sha256', $ipAddress ?? ''),
            'user_agent' => substr($userAgent ?? '', 0, 500),
            'accepted_privacy' => true,
            'accepted_at' => now(),
            'privacy_document_key' => (string) ($privacy['key'] ?? 'privacy_policy'),
            'privacy_document_version' => isset($privacy['version']) ? (string) $privacy['version'] : null,
            'privacy_document_hash' => isset($privacy['hash']) ? (string) $privacy['hash'] : null,
        ]);

        DemoRequestReceived::dispatch($demoRequest);

        return $demoRequest;
    }
}
