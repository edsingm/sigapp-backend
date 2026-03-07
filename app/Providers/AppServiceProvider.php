<?php

namespace App\Providers;

use App\Models\Central\Tenant;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Documento;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Illuminate\Support\Facades\Gate;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Regional;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\TerrenoStatus;
use App\Policies\Tenant\LegalizacaoPolicy;
use App\Policies\Tenant\LegalizacaoEtapaPolicy;
use App\Policies\Tenant\CorretorExternoPolicy;
use App\Policies\Tenant\DocumentoPolicy;
use App\Policies\Tenant\ProdutoPolicy;
use App\Policies\Tenant\ProprietarioPolicy;
use App\Policies\Tenant\ProjetoPolicy;
use App\Policies\Tenant\RegionalPolicy;
use App\Policies\Tenant\TerrenoPolicy;
use App\Policies\Tenant\TerrenoProdutoPolicy;
use App\Policies\Tenant\TerrenoStatusPolicy;
use App\Policies\Tenant\ViabilidadePolicy;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Viabilidade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);

        Gate::policy(Legalizacao::class, LegalizacaoPolicy::class);
        Gate::policy(LegalizacaoEtapa::class, LegalizacaoEtapaPolicy::class);
        Gate::policy(Terreno::class, TerrenoPolicy::class);
        Gate::policy(Documento::class, DocumentoPolicy::class);
        Gate::policy(Produto::class, ProdutoPolicy::class);
        Gate::policy(Proprietario::class, ProprietarioPolicy::class);
        Gate::policy(Projeto::class, ProjetoPolicy::class);
        Gate::policy(Regional::class, RegionalPolicy::class);
        Gate::policy(CorretorExterno::class, CorretorExternoPolicy::class);
        Gate::policy(Viabilidade::class, ViabilidadePolicy::class);
        Gate::policy(TerrenoProduto::class, TerrenoProdutoPolicy::class);
        Gate::policy(TerrenoStatus::class, TerrenoStatusPolicy::class);
    }
}
