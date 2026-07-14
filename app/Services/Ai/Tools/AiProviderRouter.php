<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Agents\SIG_IA;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AiProviderRouter
{
    /**
     * @var list<array{provider: string, model: string, success: bool, error: string|null, timestamp: Carbon}>
     */
    protected array $attempts = [];

    /**
     * Obtém o agente e a cadeia de providers para failover do Laravel AI SDK.
     *
     * @return array{agent: SIG_IA, provider: string, model: string, providers: array<string, string>, isFallback: false}
     */
    public function getAgentWithFallback(): array
    {
        $agent = new SIG_IA;
        $primaryProvider = $agent->provider();
        $primaryModel = $agent->model();
        $providers = [$primaryProvider => $primaryModel];
        $fallbackProvider = (string) config('ai.fallback_provider');
        $fallbackModel = (string) config('ai.fallback_agent_model');

        if ($fallbackProvider !== '' && $fallbackModel !== '' && $fallbackProvider !== $primaryProvider) {
            $providers[$fallbackProvider] = $fallbackModel;
        }

        return [
            'agent' => $agent,
            'provider' => $primaryProvider,
            'model' => $primaryModel,
            'providers' => $providers,
            'isFallback' => false,
        ];
    }

    /**
     * Tenta obter um agente do provider fallback.
     *
     * @return array{agent: SIG_IA|null, provider: string|null, model: string|null, isFallback: true}
     */
    public function getFallbackAgent(): array
    {
        $fallbackProvider = (string) config('ai.fallback_provider');
        $fallbackModel = (string) config('ai.fallback_agent_model');

        if (! $fallbackProvider) {
            Log::warning('AI fallback provider not configured');

            return [
                'agent' => null,
                'provider' => null,
                'model' => null,
                'isFallback' => true,
            ];
        }

        // Clona o agente e sobrescreve provider/model
        $agent = new SIG_IA;

        return [
            'agent' => $agent,
            'provider' => $fallbackProvider,
            'model' => $fallbackModel,
            'isFallback' => true,
        ];
    }

    /**
     * Registra tentativa de provider (para telemetria).
     */
    public function recordAttempt(string $provider, string $model, bool $success, ?string $error = null): void
    {
        $this->attempts[] = [
            'provider' => $provider,
            'model' => $model,
            'success' => $success,
            'error' => $error,
            'timestamp' => Carbon::now(),
        ];

        if (! $success) {
            Log::warning("AI provider failed: {$provider}/{$model} - {$error}");
        }
    }

    /**
     * Retorna as tentativas realizadas.
     *
     * @return list<array{provider: string, model: string, success: bool, error: string|null, timestamp: Carbon}>
     */
    public function getAttempts(): array
    {
        return $this->attempts;
    }
}
