<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemoRequest;
use App\Services\ApiResponseService;
use App\Services\DemoRequestService;
use Illuminate\Http\JsonResponse;

class DemoRequestController extends Controller
{
    public function __construct(
        private readonly DemoRequestService $demoRequestService,
    ) {}

    /**
     * Registra uma solicitação pública de demonstração.
     *
     * POST /api/v1/demo-request
     */
    public function store(StoreDemoRequest $request): JsonResponse
    {
        $this->demoRequestService->register(
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponseService::created(null, 'DEMO_REQUEST_RECEIVED');
    }
}
