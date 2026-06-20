<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * Transporta metadados da requisição HTTP (IP, user agent, request id) para a
 * camada de Service, sem acoplá-la ao objeto Request do Laravel.
 */
final readonly class RequestContext
{
    public function __construct(
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $requestId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: is_string($h = $request->header('X-Request-ID')) ? $h : null,
        );
    }
}
