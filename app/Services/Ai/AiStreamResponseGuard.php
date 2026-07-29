<?php

namespace App\Services\Ai;

/**
 * Detecta respostas de stream incompletas (monólogo de progresso sem entrega final).
 */
final class AiStreamResponseGuard
{
    /**
     * Padrões que indicam que o modelo anunciou um próximo passo e parou.
     *
     * @var list<string>
     */
    private const INCOMPLETE_MARKERS = [
        'vou buscar',
        'vou verificar',
        'vou consultar',
        'vou ampliar',
        'vou listar',
        'vou analisar',
        'aguarde enquanto',
        'deixe-me buscar',
        'deixe me buscar',
        'em seguida vou',
        'próximo passo',
        'proximo passo',
    ];

    public static function looksIncomplete(?string $text): bool
    {
        $normalized = mb_strtolower(trim((string) $text));
        if ($normalized === '') {
            return true;
        }

        // Texto muito curto só com promessa de ação
        foreach (self::INCOMPLETE_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                // Se termina com a promessa (últimos ~200 chars), considera incompleto
                $tail = mb_substr($normalized, -220);

                if (str_contains($tail, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function emptyFallbackMessage(): string
    {
        return 'A análise foi realizada, mas não encontrei informações relevantes para formular uma resposta. Isso pode ocorrer quando não há dados disponíveis no momento.';
    }

    public static function incompleteFallbackMessage(): string
    {
        return 'A consulta usou vários passos e a resposta ficou incompleta antes de consolidar o resultado. '
            .'Tente reformular de forma mais direta (ex.: "mostre uma viabilidade com approval_status aprovada" '
            .'ou "detalhes do terreno 123") para obter a resposta em menos etapas.';
    }
}
