<?php

namespace App\Services\Ai\Tools;

/**
 * Metadados de honestidade para saídas preditivas/heurísticas do SIG IA.
 */
final class AiPredictivePayload
{
    public const DISCLAIMER = 'Estimativa heurística com base em dados históricos do tenant. '
        .'Não constitui valor contábil, parecer formal, garantia de aprovação nem recomendação de investimento.';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function withMeta(array $payload, ?int $sampleSize = null, string $method = 'heuristic_historical'): array
    {
        $resolvedSample = $sampleSize
            ?? (isset($payload['sample_size']) ? (int) $payload['sample_size'] : null)
            ?? (isset($payload['count']) ? (int) $payload['count'] : null)
            ?? (isset($payload['benchmarks']['total_viabilidades']) ? (int) $payload['benchmarks']['total_viabilidades'] : null)
            ?? (isset($payload['similar_terrenos']['total']) ? (int) $payload['similar_terrenos']['total'] : null)
            ?? (isset($payload['total_stalled']) ? (int) $payload['total_stalled'] : null);

        return array_merge($payload, [
            'method' => $method,
            'sample_size' => $resolvedSample,
            'confidence' => $payload['confidence'] ?? null,
            'as_of' => now()->toIso8601String(),
            'disclaimer' => self::DISCLAIMER,
        ]);
    }
}
