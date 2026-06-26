<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Fonte única de verdade das categorias de notificação configuráveis pelo
 * usuário do tenant, seus canais disponíveis e os defaults.
 *
 * Notificações transacionais de conta (billing, trial, disputa, reset de senha,
 * boas-vindas) não fazem parte deste catálogo: têm o tenant como destinatário e
 * são sempre enviadas.
 */
final class NotificationCatalog
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_PUSH = 'push';

    /**
     * Categorias configuráveis e os canais disponíveis em cada uma.
     *
     * @return array<string, array{label: string, channels: array<int, string>}>
     */
    public static function categories(): array
    {
        return [
            'viabilidade.submetida' => [
                'label' => 'Viabilidade aguardando aprovação',
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            'viabilidade.decidida' => [
                'label' => 'Viabilidade aprovada ou reprovada',
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            'legalizacao.etapa.status' => [
                'label' => 'Etapa de legalização atualizada',
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            'legalizacao.etapa.atrasada' => [
                'label' => 'Etapa de legalização atrasada',
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            'projeto.finalizado' => [
                'label' => 'Projeto finalizado',
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            'contrato.assinado' => [
                'label' => 'Contrato assinado',
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            'workflow.transicao' => [
                'label' => 'Mudança de status (workflow)',
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
        ];
    }

    /**
     * Mapeia o `type` técnico emitido para a categoria de preferência.
     */
    public static function categoryForType(string $type): ?string
    {
        return match ($type) {
            'viabilidade.solicitar_aprovacao' => 'viabilidade.submetida',
            'viabilidade.aprovada', 'viabilidade.reprovada' => 'viabilidade.decidida',
            'legalizacao.etapa.status_atualizado' => 'legalizacao.etapa.status',
            'legalizacao.etapa.atrasada' => 'legalizacao.etapa.atrasada',
            'projeto.finalizado' => 'projeto.finalizado',
            'contrato.assinado' => 'contrato.assinado',
            'workflow.transicao' => 'workflow.transicao',
            default => null,
        };
    }

    public static function isValidCategory(string $category): bool
    {
        return array_key_exists($category, self::categories());
    }

    /**
     * @return array<int, string>
     */
    public static function channelsFor(string $category): array
    {
        return self::categories()[$category]['channels'] ?? [];
    }

    public static function isChannelAvailable(string $category, string $channel): bool
    {
        return in_array($channel, self::channelsFor($category), true);
    }

    /**
     * Todas as categorias operacionais vêm habilitadas por padrão.
     */
    public static function defaultEnabled(string $category, string $channel): bool
    {
        return self::isChannelAvailable($category, $channel);
    }
}
