<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\DemoRequest;

class DemoRequestRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DemoRequest
    {
        return DemoRequest::query()->create($attributes);
    }
}
