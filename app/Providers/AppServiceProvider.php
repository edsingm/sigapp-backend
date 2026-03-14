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
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Regional;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\Viabilidade;
use App\Policies\Tenant\TenantPolicy;
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
