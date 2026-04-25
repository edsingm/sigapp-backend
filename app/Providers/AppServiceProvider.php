<?php

namespace App\Providers;

use App\Models\Central\Tenant;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Regional;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\Viabilidade;
use App\Policies\Tenant\TenantPolicy;
use App\Repositories\Contracts\CentralUserRepositoryInterface;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\PlanRolePermissionTemplateRepositoryInterface;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Contracts\ProdutoRepositoryInterface;
use App\Repositories\Contracts\ProprietarioRepositoryInterface;
use App\Repositories\Contracts\RegionalRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\TerrenoExportRepositoryInterface;
use App\Repositories\Contracts\TerrenoProdutoRepositoryInterface;
use App\Repositories\Contracts\ProjetoRepositoryInterface;
use App\Repositories\CentralUserRepository;
use App\Repositories\EntitlementRepository;
use App\Repositories\PostRepository;
use App\Repositories\ProprietarioRepository;
use App\Repositories\PlanRepository;
use App\Repositories\PlanRolePermissionTemplateRepository;
use App\Repositories\TenantRepository;
use App\Repositories\ProjetoRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();

        $this->app->bind(CentralUserRepositoryInterface::class, CentralUserRepository::class);
        $this->app->bind(EntitlementRepositoryInterface::class, EntitlementRepository::class);
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(PlanRepositoryInterface::class, PlanRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, \App\Repositories\RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, \App\Repositories\PermissionRepository::class);
        $this->app->bind(ProdutoRepositoryInterface::class, \App\Repositories\ProdutoRepository::class);
        $this->app->bind(RegionalRepositoryInterface::class, \App\Repositories\RegionalRepository::class);
        $this->app->bind(TerrenoProdutoRepositoryInterface::class, \App\Repositories\TerrenoProdutoRepository::class);
        $this->app->bind(ProprietarioRepositoryInterface::class, ProprietarioRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, \App\Repositories\DashboardRepository::class);
        $this->app->bind(TerrenoExportRepositoryInterface::class, \App\Repositories\TerrenoExportRepository::class);
        $this->app->bind(PlanRolePermissionTemplateRepositoryInterface::class, PlanRolePermissionTemplateRepository::class);
        $this->app->bind(ProjetoRepositoryInterface::class, ProjetoRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);

        // All tenant models share a single policy — TenantPolicy resolves the
        // correct module/level from its MODEL_MAP using dot-notation permissions.
        $tenantModels = [
            Terreno::class,
            CorretorExterno::class,
            Regional::class,
            Produto::class,
            Proprietario::class,
            TerrenoProduto::class,
            Documento::class,
            Legalizacao::class,
            LegalizacaoEtapa::class,
            ComiteRevisao::class,
            Negociacao::class,
            Contrato::class,
            Projeto::class,
            Viabilidade::class,
        ];

        foreach ($tenantModels as $model) {
            Gate::policy($model, TenantPolicy::class);
        }
    }
}
