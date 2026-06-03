<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Terreno;
use Illuminate\Pagination\LengthAwarePaginator;

interface TerrenoFilterRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Terreno>
     */
    public function search(array $filters): LengthAwarePaginator;
}
