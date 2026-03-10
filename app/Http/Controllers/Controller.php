<?php

namespace App\Http\Controllers;

use App\Traits\LogsAudit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests, LogsAudit;
    //
    /**
     * Respond with pagination.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param string $resourceClass
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithPagination($paginator, $resourceClass)
    {
        return $resourceClass::collection($paginator)->response();
    }
}
