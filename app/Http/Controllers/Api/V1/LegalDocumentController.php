<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User as TenantUser;
use App\Services\ApiResponseService;
use App\Services\Privacy\LegalDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalDocumentController extends Controller
{
    public function __construct(
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user('sanctum');
        $userId = $actor instanceof TenantUser ? (int) $actor->getKey() : null;

        return ApiResponseService::success(
            $this->legalDocuments->catalog($userId),
            'LEGAL_DOCUMENTS_RETRIEVED',
        );
    }
}
