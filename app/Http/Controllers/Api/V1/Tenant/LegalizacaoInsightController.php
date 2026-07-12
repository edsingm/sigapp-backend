<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListLegalizacoesRequest;
use App\Http\Resources\Tenant\LegalizacaoControlCenterResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\LegalizacaoInsightService;
use Illuminate\Http\JsonResponse;

class LegalizacaoInsightController extends Controller
{
    public function __construct(
        private readonly LegalizacaoInsightService $service,
    ) {}

    public function controlCenter(ListLegalizacoesRequest $request): JsonResponse
    {
        $result = $this->service->controlCenter($request->validated());
        $result->through(fn ($legalizacao): array => (new LegalizacaoControlCenterResource($legalizacao))->resolve());

        return ApiResponseService::paginated($result, 'Central de legalização carregada com sucesso');
    }

    public function criticalPath(string $id): JsonResponse
    {
        return ApiResponseService::success($this->service->criticalPath((int) $id));
    }

    public function costs(string $id): JsonResponse
    {
        return ApiResponseService::success($this->service->costs((int) $id));
    }
}
