<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\GlobalSearchRequest;
use App\Http\Resources\Tenant\GlobalSearchResultResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class GlobalSearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $service,
    ) {}

    public function index(GlobalSearchRequest $request): JsonResponse
    {
        try {
            $result = $this->service->search(
                $request->user(),
                $request->validated('query'),
                $request->validated('types', []),
                (int) $request->validated('limit', 20),
            );
            $result->through(fn (array $row): array => (new GlobalSearchResultResource($row))->resolve());

            return ApiResponseService::paginated($result, 'Busca carregada com sucesso');
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('SEARCH_INVALID', $exception->getMessage(), null, 422);
        }
    }
}
