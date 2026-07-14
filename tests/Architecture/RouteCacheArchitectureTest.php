<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteCacheArchitectureTest extends TestCase
{
    public function test_fully_qualified_route_names_are_unique(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(static fn ($route): ?string => $route->getName())
            ->filter(static fn (?string $name): bool => $name !== null && ! str_ends_with($name, '.'))
            ->values();

        $this->assertSame($names->count(), $names->unique()->count());
    }

    public function test_primary_central_route_names_remain_canonical(): void
    {
        $this->assertSame(
            'api/v1/admin/dashboard',
            Route::getRoutes()->getByName('admin.dashboard')?->uri(),
        );
    }
}
