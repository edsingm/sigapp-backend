<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Stancl\Tenancy\Database\Models\Domain;

interface DomainRepositoryInterface
{
    public function findByDomain(string $domain): ?Domain;
}
