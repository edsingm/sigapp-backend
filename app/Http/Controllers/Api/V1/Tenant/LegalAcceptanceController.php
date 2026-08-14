<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreLegalAcceptanceRequest;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use App\Services\Privacy\LegalDocumentService;
use Illuminate\Http\JsonResponse;

class LegalAcceptanceController extends Controller
{
    public function __construct(
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    public function store(StoreLegalAcceptanceRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponseService::unauthorized();
        }

        /** @var list<string>|null $documentKeys */
        $documentKeys = $request->validated('document_keys');

        $accepted = $this->legalDocuments->recordTenantUserAcceptances(
            (int) $user->getKey(),
            $documentKeys,
        );

        return ApiResponseService::created(
            [
                'accepted' => $accepted,
                ...$this->legalDocuments->catalog((int) $user->getKey()),
            ],
            'LEGAL_ACCEPTANCES_RECORDED',
        );
    }
}
