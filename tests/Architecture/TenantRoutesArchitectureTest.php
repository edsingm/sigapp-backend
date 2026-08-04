<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantRoutesArchitectureTest extends TestCase
{
    public function test_legacy_tenant_route_contract_remains_unchanged(): void
    {
        $fixture = file_get_contents(base_path('tests/Fixtures/Routing/tenant-routes.json'));
        self::assertIsString($fixture);

        /** @var list<array{methods: list<string>, uri: string, name: ?string, action: string, middleware: list<string>, wheres: array<string, string>, binding_fields: array<string, string>}> $legacyContracts */
        $legacyContracts = json_decode($fixture, true, 512, JSON_THROW_ON_ERROR);

        $currentContracts = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $middleware = $route->middleware();

            if (! in_array('tenant', $middleware, true)
                || (! str_starts_with($route->uri(), 'api/v1/') && $route->uri() !== 'api/health')) {
                continue;
            }

            $contract = [
                'methods' => $route->methods(),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $middleware,
                'wheres' => $route->wheres,
                'binding_fields' => $route->bindingFields(),
            ];

            $currentContracts[$this->routeKey($contract['methods'], $contract['uri'])] = $contract;
        }

        foreach ($legacyContracts as $legacyContract) {
            $key = $this->routeKey($legacyContract['methods'], $legacyContract['uri']);

            self::assertArrayHasKey($key, $currentContracts, "Rota tenant legada removida: {$key}");
            self::assertSame($legacyContract, $currentContracts[$key], "Contrato da rota tenant alterado: {$key}");
        }
    }

    public function test_aggregator_loads_every_tenant_route_module_exactly_once(): void
    {
        $modulePaths = glob(base_path('routes/tenant/*.php'));
        self::assertIsArray($modulePaths);

        $expectedModules = array_map('basename', $modulePaths);
        sort($expectedModules);

        $aggregator = file_get_contents(base_path('routes/tenant.php'));
        self::assertIsString($aggregator);

        $matchCount = preg_match_all(
            "/require __DIR__\\.'\\/tenant\\/([^']+\\.php)';/",
            $aggregator,
            $matches
        );

        self::assertNotFalse($matchCount);

        $registeredModules = $matches[1];
        sort($registeredModules);

        self::assertSame(
            $expectedModules,
            $registeredModules,
            'Cada módulo em routes/tenant deve ser carregado exatamente uma vez pelo agregador.'
        );
    }

    public function test_all_versioned_tenant_routes_keep_common_context_and_rate_limits(): void
    {
        $tenantRoutes = array_filter(
            Route::getRoutes()->getRoutes(),
            static fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'api/v1/')
                && in_array('tenant', $route->middleware(), true)
        );

        self::assertNotEmpty($tenantRoutes);

        foreach ($tenantRoutes as $route) {
            $middleware = $route->middleware();

            self::assertContains(ForceJsonResponse::class, $middleware, $route->uri());
            self::assertContains(AddTenantContextToLogs::class, $middleware, $route->uri());
            self::assertContains(ApiRequestLogger::class, $middleware, $route->uri());
            self::assertContains('tenant.context', $middleware, $route->uri());

            if (in_array('throttle:api-public', $middleware, true)) {
                continue;
            }

            self::assertContains('auth:sanctum', $middleware, $route->uri());
            self::assertContains('auth.tenant', $middleware, $route->uri());
            self::assertContains('throttle:api-auth', $middleware, $route->uri());
            self::assertContains(SetUserLocale::class, $middleware, $route->uri());
        }
    }

    public function test_tenancy_provider_does_not_reload_cached_routes(): void
    {
        $provider = file_get_contents(base_path('app/Providers/TenancyServiceProvider.php'));
        self::assertIsString($provider);

        self::assertStringContainsString(
            '$this->app->routesAreCached()',
            $provider
        );
    }

    public function test_specific_routes_keep_precedence_over_dynamic_resources(): void
    {
        $routeOrder = [];

        foreach (Route::getRoutes()->getRoutes() as $index => $route) {
            if (in_array('tenant', $route->middleware(), true)) {
                $routeOrder[$route->uri()] ??= $index;
            }
        }

        $precedencePairs = [
            ['api/v1/tasks/my-queue', 'api/v1/tasks/{task}'],
            ['api/v1/terrenos/compare', 'api/v1/terrenos/{terreno}'],
            ['api/v1/terrenos/pipeline', 'api/v1/terrenos/{terreno}'],
            ['api/v1/terrenos/filter', 'api/v1/terrenos/{terreno}'],
            ['api/v1/terrenos/select', 'api/v1/terrenos/{terreno}'],
            ['api/v1/terrenos/imports/template', 'api/v1/terrenos/{terreno}'],
            ['api/v1/terrenos/polygon-imports/{import}', 'api/v1/terrenos/{terreno}'],
            ['api/v1/terrenos/polygons/{polygon}', 'api/v1/terrenos/{terreno}'],
            ['api/v1/viabilidades/for-select', 'api/v1/viabilidades/{viabilidade}'],
            ['api/v1/viabilidades/modelos-financiamento', 'api/v1/viabilidades/{viabilidade}'],
            ['api/v1/viabilidades/terreno/{terrenoId}', 'api/v1/viabilidades/{viabilidade}'],
            ['api/v1/viabilidades/compare', 'api/v1/viabilidades/{viabilidade}'],
            ['api/v1/tenant-admin/roles/select', 'api/v1/tenant-admin/roles/{role}'],
            ['api/v1/tenant-admin/departments/select', 'api/v1/tenant-admin/departments/{department}'],
            ['api/v1/legalizacoes/eligible-terrenos', 'api/v1/legalizacoes/{legalizaco}'],
            ['api/v1/legalizacoes/control-center', 'api/v1/legalizacoes/{legalizaco}'],
        ];

        foreach ($precedencePairs as [$specific, $dynamic]) {
            self::assertArrayHasKey($specific, $routeOrder);
            self::assertArrayHasKey($dynamic, $routeOrder);
            self::assertLessThan(
                $routeOrder[$dynamic],
                $routeOrder[$specific],
                "A rota específica {$specific} deve vir antes de {$dynamic}."
            );
        }

        $committeeRoute = Route::getRoutes()->getByAction(
            'App\\Http\\Controllers\\Api\\V1\\Tenant\\CommitteeController@show'
        );
        self::assertNotNull($committeeRoute);
        self::assertSame('[0-9]+', $committeeRoute->wheres['id'] ?? null);

        self::assertArrayHasKey('api/health', $routeOrder);
        self::assertArrayNotHasKey('api/v1/health', $routeOrder);
    }

    /**
     * @param  array<int, string>  $methods
     */
    private function routeKey(array $methods, string $uri): string
    {
        return implode('|', $methods).' '.$uri;
    }
}
