<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Enums\Common\EntitlementScope;
use App\Models\Central\Entitlement;
use App\Support\EntitlementCatalog;
use Database\Seeders\EntitlementSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EntitlementCatalogArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_catalog_and_route_gates_are_consistent(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(EntitlementSeeder::class);

        $catalog = Entitlement::query()->get()->keyBy('key');
        $gates = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'check.feature:')) {
                    $gates[] = substr($middleware, strlen('check.feature:'));
                }
            }
        }

        $gates = array_values(array_unique($gates));
        foreach ($gates as $gate) {
            $canonical = EntitlementCatalog::canonicalKey($gate);
            self::assertTrue($catalog->has($canonical), "Gate desconhecido: {$gate}");
        }

        $enforced = array_values(array_unique([
            ...array_map(EntitlementCatalog::canonicalKey(...), $gates),
            ...EntitlementCatalog::RESPONSE_PROJECTIONS,
        ]));

        $missing = $catalog->where('scope', EntitlementScope::API)
            ->pluck('key')
            ->diff($enforced)
            ->values()
            ->all();

        self::assertSame([], $missing, 'Features API sem gate ou projeção registrada.');
    }
}
