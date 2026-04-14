<?php

namespace App\Http\Controllers;

use App\Traits\LogsAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    use AuthorizesRequests, LogsAudit;

    //
    /**
     * Responde com paginação.
     *
     * @param  LengthAwarePaginator  $paginator
     * @param  string  $resourceClass
     * @return JsonResponse
     */
    protected function respondWithPagination($paginator, $resourceClass)
    {
        return $resourceClass::collection($paginator)->response();
    }
}
