<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPrivacyRequestsRequest;
use App\Http\Requests\Admin\StorePrivacyRequestRequest;
use App\Http\Requests\Admin\UpdatePrivacyRequestRequest;
use App\Http\Resources\Admin\PrivacyRequestResource;
use App\Services\ApiResponseService;
use App\Services\Privacy\PrivacyRequestService;
use App\Traits\LogsAudit;
use Illuminate\Http\JsonResponse;

class PrivacyRequestController extends Controller
{
    use LogsAudit;

    public function __construct(
        private readonly PrivacyRequestService $requests,
    ) {}

    public function index(ListPrivacyRequestsRequest $request): JsonResponse
    {
        $paginator = $this->requests->paginate(
            $request->validated(),
            min(100, max(1, (int) $request->integer('per_page', 20))),
        )->through(fn ($item): array => PrivacyRequestResource::make($item)->resolve());

        return ApiResponseService::paginated($paginator, 'PRIVACY_REQUESTS_RETRIEVED');
    }

    public function store(StorePrivacyRequestRequest $request): JsonResponse
    {
        $created = $this->requests->create($request->validated());

        $this->audit('privacy.request_opened', 'Pedido DSAR aberto a partir do canal do DPO.', [
            'privacy_request_id' => $created->id,
            'protocol' => $created->protocol,
        ]);

        return ApiResponseService::created(
            PrivacyRequestResource::make($created)->resolve(),
            'PRIVACY_REQUEST_CREATED',
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponseService::success(
            PrivacyRequestResource::make($this->requests->find($id))->resolve(),
            'PRIVACY_REQUEST_RETRIEVED',
        );
    }

    public function update(UpdatePrivacyRequestRequest $request, int $id): JsonResponse
    {
        $updated = $this->requests->update($this->requests->find($id), $request->validated());

        $this->audit('privacy.request_updated', 'Pedido DSAR atualizado.', [
            'privacy_request_id' => $updated->id,
            'protocol' => $updated->protocol,
            'status' => $updated->status->value,
        ]);

        return ApiResponseService::success(
            PrivacyRequestResource::make($updated)->resolve(),
            'PRIVACY_REQUEST_UPDATED',
        );
    }
}
