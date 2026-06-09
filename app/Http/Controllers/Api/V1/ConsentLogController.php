<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsentLogRequest;
use App\Models\ConsentLog;
use App\Services\ApiResponseService;
use Symfony\Component\HttpFoundation\Response;

class ConsentLogController extends Controller
{
    /**
     * Registrar consentimento de cookies (LGPD — accountability).
     *
     * Endpoint público: não requer autenticação.
     * IP é armazenado apenas como hash SHA-256 para anonimização (LGPD Art. 12).
     */
    public function store(StoreConsentLogRequest $request): Response
    {
        $data = $request->validated();

        ConsentLog::create([
            'consent_id'   => $data['consent_id'],
            'categories'   => $data['categories'],
            'version'      => $data['version'],
            'ip_hash'      => hash('sha256', $request->ip() ?? ''),
            'user_agent'   => substr($request->userAgent() ?? '', 0, 500),
            'consented_at' => $data['timestamp'],
        ]);

        return ApiResponseService::created(['consent_id' => $data['consent_id']]);
    }
}
