<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsentLogRequest;
use App\Services\ApiResponseService;
use App\Services\ConsentLogService;
use Symfony\Component\HttpFoundation\Response;

class ConsentLogController extends Controller
{
    public function __construct(
        private readonly ConsentLogService $consentLogService,
    ) {}

    /**
     * Registrar consentimento de cookies (LGPD — accountability).
     *
     * Endpoint público: não requer autenticação.
     * IP é armazenado apenas como hash SHA-256 para anonimização (LGPD Art. 12).
     */
    public function store(StoreConsentLogRequest $request): Response
    {
        $consentLog = $this->consentLogService->register(
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        $payload = ['consent_id' => $consentLog->getAttribute('consent_id')];

        return $consentLog->wasRecentlyCreated
            ? ApiResponseService::created($payload)
            : ApiResponseService::success($payload);
    }
}
