<?php

declare(strict_types=1);

namespace App\Providers;

use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;
use Stancl\JobPipeline\JobPipeline;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
// use Stancl\Tenancy\Actions\CloneRoutesAsTenant; // Classe não existe na v3.9.1
use Illuminate\Support\Facades\Route;

/**
 * Tenancy para Laravel.
 *
 * Documentação: https://tenancyforlaravel.com
 *
 * Podemos desenvolver o Tenancy para Laravel de forma sustentável graças aos nossos patrocinadores.
 * Um grande agradecimento a todos listados aqui: https://github.com/sponsors/stancl
 *
 * Você também pode nos apoiar e economizar tempo adquirindo estes produtos:
 *   Conteúdo exclusivo para patrocinadores: https://sponsors.tenancyforlaravel.com
 *   Boilerplate SaaS Multi-Tenant: https://portal.archte.ch/boilerplate
 *   E-book Multi-Tenant Laravel em Produção: https://portal.archte.ch/book
 *
 * Todos esses produtos também podem ser acessados em https://portal.archte.ch
 */
class TenancyServiceProvider extends ServiceProvider
{
    // Por padrão, nenhum namespace é usado para suportar a sintaxe de array chamável.
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
                // Eventos de Tenant
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                JobPipeline::make([
                    // Jobs\CreateDatabase::class,
                    // Jobs\MigrateDatabase::class,
                    // Jobs\SeedDatabase::class,
                    // Jobs\CreateStorageSymlinks::class,

                    // Seus próprios jobs para preparar o tenant.
                    // Provisionar chaves de API, criar buckets S3, o que você quiser!
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),

                // Listeners\CreateTenantStorage::class,
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [
                JobPipeline::make([
                        // Jobs\DeleteDomains::class, // Classe não existe na v3.9
                        // Jobs\RemoveStorageSymlinks::class,
                    Jobs\DeleteDatabase::class, // Adicionado manualmente
                ])->send(function (Events\DeletingTenant $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),

                // Listeners\DeleteTenantStorage::class,
            ],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    // Jobs\DeleteDatabase::class, // Movido para DeletingTenant
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),

                // ResourceSyncing\Listeners\DeleteAllTenantMappings::class,
            ],

                // Eventos de modo de manutenção não disponíveis na v3.9
                // Events\TenantMaintenanceModeEnabled::class => [],
                // Events\TenantMaintenanceModeDisabled::class => [],

                // Eventos de tenant pendente não disponíveis na v3.9
                // Events\CreatingPendingTenant::class => [],
                // Events\PendingTenantCreated::class => [],
                // Events\PullingPendingTenant::class => [],
                // Events\PendingTenantPulled::class => [],

                // Eventos de domínio
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

                // Eventos de banco de dados
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

                // Eventos de Tenancy
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            // Sincronização de recursos não disponível na v3.9
            // ResourceSyncing\Events\SyncedResourceSaved::class => [
            //     ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::class,
            // ],
            // ResourceSyncing\Events\SyncedResourceDeleted::class => [
            //     ResourceSyncing\Listeners\DeleteResourceMapping::class,
            // ],
            // ResourceSyncing\Events\SyncMasterDeleted::class => [
            //     ResourceSyncing\Listeners\DeleteResourcesInTenants::class,
            // ],
            // ResourceSyncing\Events\SyncMasterRestored::class => [
            //     ResourceSyncing\Listeners\RestoreResourcesInTenants::class,
            // ],
            // ResourceSyncing\Events\CentralResourceAttachedToTenant::class => [
            //     ResourceSyncing\Listeners\CreateTenantResource::class,
            // ],
            // ResourceSyncing\Events\CentralResourceDetachedFromTenant::class => [
            //     ResourceSyncing\Listeners\DeleteResourceInTenant::class,
            // ],

            // Disparado apenas quando um recurso sincronizado é alterado (como resultado da sincronização)
            // em um banco de dados diferente do banco de dados de onde a alteração se origina (para evitar loops infinitos)
            // ResourceSyncing\Events\SyncedResourceSavedInForeignDatabase::class => [],

            // Eventos de links simbólicos de armazenamento não disponíveis na v3.9
            // Events\CreatingStorageSymlink::class => [],
            // Events\StorageSymlinkCreated::class => [],
            // Events\RemovingStorageSymlink::class => [],
            // Events\StorageSymlinkRemoved::class => [],
        ];
    }

    /**
     * Defina \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper::$rootUrlOverride aqui
     * para sobrescrever a URL raiz usada no CLI enquanto estiver no contexto do tenant.
     *
     * @see \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper
     */
    protected function overrideUrlInTenantContext(): void
    {
        // \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper::$rootUrlOverride = function (Tenant $tenant, string $originalRootUrl) {
        //     $tenantDomain = $tenant instanceof \Stancl\Tenancy\Contracts\SingleDomainTenant
        //         ? $tenant->domain
        //         : $tenant->domains->first()->domain;
        //
        //     if (is_null($tenantDomain)) {
        //         return $originalRootUrl;
        //     }
        //
        //     $scheme = str($originalRootUrl)->before('://');
        //
        //     if (str_contains($tenantDomain, '.')) {
        //         // Identificação por domínio
        //         return $scheme . '://' . $tenantDomain . '/';
        //     } else {
        //         // Identificação por subdomínio
        //         $originalDomain = str($originalRootUrl)->after($scheme . '://')->before('/');
        //         return $scheme . '://' . $tenantDomain . '.' . $originalDomain . '/';
        //     }
        // };
    }

    public function register()
    {
        // Configura o identificador de banco/schema do tenant usando o slug.
        \Stancl\Tenancy\DatabaseConfig::generateDatabaseNamesUsing(function ($tenant) {
            return \App\Models\Central\Tenant::makeTenantDatabaseIdentifier((string) $tenant->slug);
        });
    }

    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->overrideUrlInTenantContext();

        // // Incluir recursos excluídos logicamente (soft deleted) em consultas de recursos sincronizados.
        // ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::$scopeGetModelQuery = function (Builder $query) {
        //     if ($query->hasMacro('withTrashed')) {
        //         $query->withTrashed();
        //     }
        // };

        // // Para fazer o Livewire v3 funcionar com Tenancy, torne a rota de atualização universal.
        // Livewire::setUpdateRoute(function ($handle) {
        //     return Route::post('/livewire/update', $handle)->middleware(['web', 'universal', \Stancl\Tenancy\Tenancy::defaultMiddleware()]);
        // });
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->middleware('tenant')
                    ->group(base_path('routes/tenant.php'));
            }

            // $this->cloneRoutes();
        });
    }

    /**
     * Clonar rotas como tenant.
     *
     * Isso é usado principalmente para integrar pacotes.
     *
     * @see CloneRoutesAsTenant
     */
    protected function cloneRoutes(): void
    {
        // CloneRoutesAsTenant não disponível na v3.9.1
        // /** @var CloneRoutesAsTenant $cloneRoutes */
        // $cloneRoutes = $this->app->make(CloneRoutesAsTenant::class);
        // /** See CloneRoutesAsTenant for usage details. */
        // $cloneRoutes->handle();
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        // PreventAccessFromUnwantedDomains não disponível na v3.9.1
        // $tenancyMiddleware = array_merge([Middleware\PreventAccessFromUnwantedDomains::class], config('tenancy.identification.middleware'));
        $tenancyMiddleware = config('tenancy.identification.middleware');

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
