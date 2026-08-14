<?php

namespace App\Providers;

use App\Encryption\TenantEncrypter;
use App\Models\Central\Subscription;
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
use App\Observers\Tenant\TerrenoObserver;
use App\Policies\Tenant\TenantPolicy;
use App\Repositories\AdminMfaRepository;
use App\Repositories\AiAnomalyRepository;
use App\Repositories\AiCreditTransactionRepository;
use App\Repositories\AiPredictiveRepository;
use App\Repositories\AiTelemetryRepository;
use App\Repositories\BillingAddonRepository;
use App\Repositories\CentralUserRepository;
use App\Repositories\Contracts\AdminMfaRepositoryInterface;
use App\Repositories\Contracts\AiAnomalyRepositoryInterface;
use App\Repositories\Contracts\AiCreditTransactionRepositoryInterface;
use App\Repositories\Contracts\AiPredictiveRepositoryInterface;
use App\Repositories\Contracts\AiTelemetryRepositoryInterface;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use App\Repositories\Contracts\CentralUserRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\DomainRepositoryInterface;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;
use App\Repositories\Contracts\MobileDeviceInstallationRepositoryInterface;
use App\Repositories\Contracts\MobileNotificationRepositoryInterface;
use App\Repositories\Contracts\ModulesRepositoryInterface;
use App\Repositories\Contracts\NotificationPreferenceRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\PlanRolePermissionTemplateRepositoryInterface;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use App\Repositories\Contracts\ProdutoRepositoryInterface;
use App\Repositories\Contracts\ProjetoRepositoryInterface;
use App\Repositories\Contracts\ProprietarioRepositoryInterface;
use App\Repositories\Contracts\RegionalRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\TenantAddonPurchaseRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\TerrenoExportRepositoryInterface;
use App\Repositories\Contracts\TerrenoFilterRepositoryInterface;
use App\Repositories\Contracts\TerrenoProdutoRepositoryInterface;
use App\Repositories\Contracts\TerrenoRepositoryInterface;
use App\Repositories\Contracts\UsageMetricsRepositoryInterface;
use App\Repositories\Contracts\ViabilidadeRepositoryInterface;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;
use App\Repositories\DashboardRepository;
use App\Repositories\DomainRepository;
use App\Repositories\EntitlementRepository;
use App\Repositories\MobileDeviceInstallationRepository;
use App\Repositories\MobileNotificationRepository;
use App\Repositories\ModulesRepository;
use App\Repositories\NotificationPreferenceRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PlanRepository;
use App\Repositories\PlanRolePermissionTemplateRepository;
use App\Repositories\PostRepository;
use App\Repositories\PremissasViabilidadeRepository;
use App\Repositories\ProdutoRepository;
use App\Repositories\ProjetoRepository;
use App\Repositories\ProprietarioRepository;
use App\Repositories\RegionalRepository;
use App\Repositories\RoleRepository;
use App\Repositories\Tenant\LandWorkflowRepository;
use App\Repositories\Tenant\TerrenoFilterRepository;
use App\Repositories\Tenant\TerrenoRepository;
use App\Repositories\Tenant\UsageMetricsRepository;
use App\Repositories\Tenant\ViabilidadeRepository;
use App\Repositories\TenantAddonPurchaseRepository;
use App\Repositories\TenantAddonSubscriptionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TerrenoExportRepository;
use App\Repositories\TerrenoProdutoRepository;
use App\Repositories\WebhookEventRepository;
use App\Services\ApiResponseService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(TenantEncrypter::class);

        $this->app->bind(AiAnomalyRepositoryInterface::class, AiAnomalyRepository::class);
        $this->app->bind(AiPredictiveRepositoryInterface::class, AiPredictiveRepository::class);
        $this->app->bind(AiTelemetryRepositoryInterface::class, AiTelemetryRepository::class);
        $this->app->bind(AiCreditTransactionRepositoryInterface::class, AiCreditTransactionRepository::class);
        $this->app->bind(AdminMfaRepositoryInterface::class, AdminMfaRepository::class);
        $this->app->bind(CentralUserRepositoryInterface::class, CentralUserRepository::class);
        $this->app->bind(BillingAddonRepositoryInterface::class, BillingAddonRepository::class);
        $this->app->bind(EntitlementRepositoryInterface::class, EntitlementRepository::class);
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(PlanRepositoryInterface::class, PlanRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(TenantAddonSubscriptionRepositoryInterface::class, TenantAddonSubscriptionRepository::class);
        $this->app->bind(TenantAddonPurchaseRepositoryInterface::class, TenantAddonPurchaseRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(ProdutoRepositoryInterface::class, ProdutoRepository::class);
        $this->app->bind(RegionalRepositoryInterface::class, RegionalRepository::class);
        $this->app->bind(TerrenoProdutoRepositoryInterface::class, TerrenoProdutoRepository::class);
        $this->app->bind(ProprietarioRepositoryInterface::class, ProprietarioRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(TerrenoExportRepositoryInterface::class, TerrenoExportRepository::class);
        $this->app->bind(PlanRolePermissionTemplateRepositoryInterface::class, PlanRolePermissionTemplateRepository::class);
        $this->app->bind(ProjetoRepositoryInterface::class, ProjetoRepository::class);
        $this->app->bind(PremissasViabilidadeRepositoryInterface::class, PremissasViabilidadeRepository::class);
        $this->app->bind(DomainRepositoryInterface::class, DomainRepository::class);
        $this->app->bind(WebhookEventRepositoryInterface::class, WebhookEventRepository::class);
        $this->app->bind(TerrenoRepositoryInterface::class, TerrenoRepository::class);
        $this->app->bind(ViabilidadeRepositoryInterface::class, ViabilidadeRepository::class);
        $this->app->bind(TerrenoFilterRepositoryInterface::class, TerrenoFilterRepository::class);
        $this->app->bind(MobileDeviceInstallationRepositoryInterface::class, MobileDeviceInstallationRepository::class);
        $this->app->bind(ModulesRepositoryInterface::class, ModulesRepository::class);
        $this->app->bind(MobileNotificationRepositoryInterface::class, MobileNotificationRepository::class);
        $this->app->bind(NotificationPreferenceRepositoryInterface::class, NotificationPreferenceRepository::class);
        $this->app->bind(LandWorkflowRepositoryInterface::class, LandWorkflowRepository::class);
        $this->app->bind(UsageMetricsRepositoryInterface::class, UsageMetricsRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        Cashier::useSubscriptionModel(Subscription::class);
        $this->configureRateLimiting();

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

        // Observer para cálculo automático de área útil
        Terreno::observe(TerrenoObserver::class);

        Gate::define('viewApiDocs', fn () => app()->environment('local'));

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
                    ->setDescription('Insira o token no formato: Bearer seu_token_aqui')
            );
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('consent-log', fn (Request $request) => Limit::perMinute(5)
            ->by('consent-log:'.$request->ip())
            ->response(fn () => ApiResponseService::tooManyRequests('Muitos registros de consentimento em curto período. Tente novamente em 1 minuto.')));

        RateLimiter::for('demo-request', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(5)
                    ->by('demo-request:ip:'.$request->ip())
                    ->response(fn () => ApiResponseService::tooManyRequests('Muitas solicitações de demonstração. Tente novamente em 1 minuto.')),
                Limit::perMinutes(10, 3)
                    ->by('demo-request:email:'.sha1($email))
                    ->response(fn () => ApiResponseService::tooManyRequests('Muitas solicitações para este e-mail. Tente novamente mais tarde.')),
            ];
        });

        RateLimiter::for('api-auth', function (Request $request) {
            $user = $request->user();
            $tenantId = tenancy()->initialized ? (string) tenant('id') : null;
            $key = $tenantId
                ? ($user ? "tenant:{$tenantId}:user:{$user->id}" : "tenant:{$tenantId}:ip:{$request->ip()}")
                : ($user ? "central:user:{$user->id}" : "central:ip:{$request->ip()}");

            return Limit::perMinute(1000)
                ->by($key)
                ->response(fn () => response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TOO_MANY_REQUESTS',
                        'message' => 'Muitas requisições. Tente novamente em 1 minuto.',
                    ],
                ], 429));
        });

        RateLimiter::for('terrain-imports', function (Request $request) {
            $user = $request->user();
            $tenantId = tenancy()->initialized ? (string) tenant('id') : 'no-tenant';
            $key = $user
                ? "terrain-imports:{$tenantId}:user:{$user->id}"
                : "terrain-imports:{$tenantId}:ip:{$request->ip()}";

            return Limit::perMinute(5)
                ->by($key)
                ->response(fn () => ApiResponseService::tooManyRequests('TERRAIN_IMPORT_RATE_LIMITED'));
        });

        RateLimiter::for('hiperdados-imports', function (Request $request) {
            $user = $request->user();
            $key = $user
                ? 'hiperdados-imports:user:'.$user->id
                : 'hiperdados-imports:ip:'.$request->ip();

            return Limit::perMinute(3)
                ->by($key)
                ->response(fn () => ApiResponseService::tooManyRequests('HIPERDADOS_IMPORT_RATE_LIMITED'));
        });

        RateLimiter::for('central-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(5)
                ->by('central-login:'.$request->ip().':'.sha1($email))
                ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas de login. Tente novamente em 1 minuto.'));
        });

        RateLimiter::for('central-login-select', fn (Request $request) => Limit::perMinute(10)
            ->by('central-login-select:'.$request->ip())
            ->response(fn () => ApiResponseService::tooManyRequests()));

        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(5)
                ->by('admin-login:'.$request->ip().':'.sha1($email))
                ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas de login de administrador. Tente novamente em 1 minuto.'));
        });

        RateLimiter::for('admin-mfa', function (Request $request) {
            $challenge = strtolower(trim((string) $request->input('challenge', '')));
            $challengeKey = $challenge !== '' ? hash('sha256', $challenge) : 'missing';

            return [
                Limit::perMinutes(10, 20)
                    ->by('admin-mfa:ip:'.$request->ip())
                    ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas de MFA. Aguarde alguns minutos.')),
                Limit::perMinutes(10, 5)
                    ->by('admin-mfa:challenge:'.$challengeKey)
                    ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas para este desafio MFA.')),
            ];
        });

        RateLimiter::for('transfer-ticket', function (Request $request) {
            $tenantKey = tenancy()->initialized ? (string) tenant('id') : 'no-tenant';

            return Limit::perMinute(15)
                ->by('transfer-ticket:'.$tenantKey.':'.$request->ip())
                ->response(fn () => ApiResponseService::tooManyRequests());
        });

        RateLimiter::for('password-reset-request', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(5)
                ->by('password-reset-request:'.$request->ip().':'.sha1($email))
                ->response(fn () => ApiResponseService::tooManyRequests('Muitas solicitações de redefinição. Tente novamente em 1 minuto.'));
        });

        RateLimiter::for('password-reset-submit', fn (Request $request) => Limit::perMinute(10)
            ->by('password-reset-submit:'.$request->ip())
            ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas de redefinição. Tente novamente em 1 minuto.')));

        RateLimiter::for('signup-status', function (Request $request) {
            $sessionParameter = $request->route('sessionId', '');
            $sessionId = is_scalar($sessionParameter) ? (string) $sessionParameter : '';

            return Limit::perMinute(30)
                ->by('signup-status:'.$request->ip().':'.sha1($sessionId))
                ->response(fn () => ApiResponseService::tooManyRequests('Muitas consultas de status. Aguarde 1 minuto.'));
        });

        RateLimiter::for('viabilidade-approval', function (Request $request) {
            $user = $request->user();
            $tenantId = tenancy()->initialized ? (string) tenant('id') : 'no-tenant';
            $key = $user
                ? "viabilidade-approval:{$tenantId}:user:{$user->id}"
                : "viabilidade-approval:{$tenantId}:ip:{$request->ip()}";

            return Limit::perMinute(10)
                ->by($key)
                ->response(fn () => ApiResponseService::tooManyRequests('Muitas ações de aprovação em curto período. Aguarde 1 minuto.'));
        });

        RateLimiter::for('exports', function (Request $request) {
            $user = $request->user();
            $tenantId = tenancy()->initialized ? (string) tenant('id') : 'no-tenant';
            $key = $user
                ? "exports:{$tenantId}:user:{$user->id}"
                : "exports:{$tenantId}:ip:{$request->ip()}";

            return Limit::perMinute(10)
                ->by($key)
                ->response(fn () => ApiResponseService::tooManyRequests('Muitas exportações solicitadas em curto período. Aguarde 1 minuto.'));
        });
    }
}
