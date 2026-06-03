<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\LegalizacaoEtapaOverdue;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Tenant\MobilePushService;

class NotifyOverdueLegalizacaoEtapa
{
    public function __construct(
        private readonly MobilePushService $mobilePushService,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(LegalizacaoEtapaOverdue $event): void
    {
        $etapa = $event->etapa;
        $today = now()->startOfDay();

        $dedupeKey = sprintf(
            'legalizacao-etapa-atrasada:%s:%s',
            $etapa->id,
            $today->format('Y-m-d'),
        );

        $payload = [
            'title' => 'Etapa de legalização atrasada',
            'body' => sprintf(
                '%s em %s está atrasada.',
                $etapa->nome,
                $etapa->legalizacao?->terreno?->nome ?? 'terreno sem nome',
            ),
            'type' => 'legalizacao.etapa.atrasada',
            'entity_type' => 'legalizacao_etapa',
            'entity_id' => (string) $etapa->id,
            'target_route' => $etapa->legalizacao?->terreno_id
                ? "/terrenos/{$etapa->legalizacao->terreno_id}"
                : '/notifications',
            'payload' => [
                'legalizacao_id' => $etapa->legalizacao_id,
                'etapa_id' => $etapa->id,
                'tenant_slug' => $event->tenantSlug,
            ],
        ];

        if ($etapa->responsavel) {
            $this->mobilePushService->notifyUsers([$etapa->responsavel], $payload, $dedupeKey);

            return;
        }

        $this->mobilePushService->notifyUsersWithPermission(
            (string) $this->permissions->forModel(LegalizacaoEtapa::class, 'update'),
            $payload,
            null,
            $dedupeKey,
        );
    }
}
