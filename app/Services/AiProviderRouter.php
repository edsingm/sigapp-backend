<?php

namespace App\Services;

use App\Ai\Agents\SIG_IA;
use Illuminate\Support\Facades\Log;

class AiProviderRouter
{
    protected array $attempts = [];

    /**
     * Obtém o agente configurado com provider primário.
     */
    public function getAgentWithFallback(): array
    {
        $agent = new SIG_IA;
        $primaryProvider = $agent->provider();
        $primaryModel = $agent->model();

        return [
            'agent' => $agent,
            'provider' => $primaryProvider,
            'model' => $primaryModel,
            'isFallback' => false,
        ];
    }

    /**
     * Tenta obter um agente do provider fallback.
     */
    public function getFallbackAgent(): array
    {
        $fallbackProvider = env('AI_FALLBACK_PROVIDER', 'anthropic');
        $fallbackModel = env('AI_FALLBACK_AGENT_MODEL', 'claude-sonnet-4-6');

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
            'timestamp' => now(),
        ];

        if (! $success) {
            Log::warning("AI provider failed: {$provider}/{$model} - {$error}");
        }
    }

    /**
     * Retorna as tentativas realizadas.
     */
    public function getAttempts(): array
    {
        return $this->attempts;
    }
}
