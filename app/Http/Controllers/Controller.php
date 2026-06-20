<?php

namespace App\Http\Controllers;

use App\Traits\LogsAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    use AuthorizesRequests, LogsAudit;

    /**
     * Responde com paginação aplicando o Resource informado à coleção.
     *
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function respondWithPagination(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        return $resourceClass::collection($paginator)->response();
    }
}
