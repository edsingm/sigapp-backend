<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\DomainRepositoryInterface;
use Stancl\Tenancy\Database\Models\Domain;

class DomainRepository implements DomainRepositoryInterface
{
    public function findByDomain(string $domain): ?Domain
    {
        return Domain::query()->where('domain', $domain)->first();
    }
}
