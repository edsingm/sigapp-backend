<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\PlanMatrixService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Helpers de autorização/plano/rate-limit para AI tools.
 *
 * Retorna string de envelope pronta para `return` quando negar; null quando ok.
 */
final class AiToolAuth
{
    public function __construct(
        private readonly PlanMatrixService $planMatrix,
    ) {}

    public function ensureAuthenticated(
        string $message = 'Acesso negado: autenticação necessária.'
    ): ?string {
        if (! auth()->check()) {
            return AiToolResponse::denied($message);
        }

        return null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function ensureViewAny(string $modelClass, string $message): ?string
    {
        if (! $this->canViewAny($modelClass)) {
            return AiToolResponse::denied($message);
        }

        return null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function canViewAny(string $modelClass): bool
    {
        return Gate::allows('viewAny', $modelClass);
    }

    public function ensureView(Model $model, string $message): ?string
    {
        if (Gate::denies('view', $model)) {
            return AiToolResponse::denied($message);
        }

        return null;
    }

    public function ensureUpdate(Model $model, string $message): ?string
    {
        if (Gate::denies('update', $model)) {
            return AiToolResponse::denied($message);
        }

        return null;
    }

    /**
     * Exige tenant inicializado + feature de plano.
     */
    public function ensureFeature(string $featureKey, string $message): ?string
    {
        if (! $this->canUseFeature($featureKey)) {
            return AiToolResponse::planDenied($message);
        }

        return null;
    }

    public function canUseFeature(string $featureKey): bool
    {
        $tenant = tenancy()->tenant;

        return $tenant !== null
            && $this->planMatrix->hasFeatureForTenant($tenant, $featureKey);
    }

    /**
     * Carrega terreno e exige permissão de visualização.
     *
     * @return Terreno|string Terreno quando ok; envelope string quando negar/vazio.
     */
    public function ensureTerrenoView(
        int $terrenoId,
        string $deniedMessage = 'Acesso negado: você não tem permissão para acessar terrenos.',
    ): Terreno|string {
        if ($terrenoId <= 0) {
            return AiToolResponse::validation('Informe um terreno_id válido.');
        }

        if ($deny = $this->ensureViewAny(Terreno::class, $deniedMessage)) {
            return $deny;
        }

        $terreno = Terreno::query()->find($terrenoId);
        if (! $terreno) {
            return AiToolResponse::empty("Terreno {$terrenoId} não encontrado.");
        }

        if ($deny = $this->ensureView(
            $terreno,
            "Acesso negado: você não tem permissão para visualizar o terreno {$terrenoId}."
        )) {
            return $deny;
        }

        return $terreno;
    }

    /**
     * Rate limit por tenant+usuário. Consome 1 hit em sucesso de checagem.
     */
    public function ensureRateLimit(
        string $bucket,
        int $maxAttempts,
        int $decaySeconds,
        string $message,
    ): ?string {
        $tenantId = tenancy()->initialized ? (string) (tenant('id') ?? 'tenant') : 'central';
        $userId = (string) (auth()->id() ?? 'guest');
        $key = "{$bucket}:{$tenantId}:{$userId}";

        if (RateLimiter::tooManyAttempts($key, max(1, $maxAttempts))) {
            $seconds = RateLimiter::availableIn($key);

            return AiToolResponse::error(
                $message.($seconds > 0 ? " Tente novamente em {$seconds}s." : '')
            );
        }

        RateLimiter::hit($key, max(1, $decaySeconds));

        return null;
    }

    /**
     * Filtra itens cujo modelo resolvido o usuário pode visualizar.
     *
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @param  callable(T): (?Model)  $modelResolver
     * @return Collection<int, T>
     */
    public function filterByView(Collection $items, callable $modelResolver): Collection
    {
        return $items
            ->filter(function ($item) use ($modelResolver): bool {
                $model = $modelResolver($item);
                if (! $model instanceof Model) {
                    return false;
                }

                return Gate::allows('view', $model);
            })
            ->values();
    }
}
