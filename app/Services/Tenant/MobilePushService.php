<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\LegalizacaoEtapaStatus;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\MobileDeviceInstallation;
use App\Models\Tenant\MobileNotification;
use App\Models\Tenant\User;
use App\Repositories\Contracts\MobileDeviceInstallationRepositoryInterface;
use App\Repositories\Contracts\MobileNotificationRepositoryInterface;
use App\Repositories\Tenant\LegalizacaoEtapaRepository;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobilePushService
{
    public function __construct(
        private readonly PermissionNameResolver $permissions,
        private readonly MobileDeviceInstallationRepositoryInterface $deviceRepository,
        private readonly MobileNotificationRepositoryInterface $notificationRepository,
        private readonly UserRepository $userRepository,
        private readonly LegalizacaoEtapaRepository $legalizacaoEtapaRepository,
    ) {}

    /**
     * Registra ou atualiza um dispositivo móvel para um usuário.
     */
    public function registerDevice(User $user, array $attributes): MobileDeviceInstallation
    {
        $device = $this->deviceRepository->updateOrCreateByInstallationId(
            (string) $attributes['installation_id'],
            [
                'user_id' => $user->id,
                'platform' => (string) ($attributes['platform'] ?? 'ios'),
                'device_name' => $attributes['device_name'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'expo_push_token' => $attributes['expo_push_token'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        if ($device->user_id !== $user->id) {
            $device = $this->deviceRepository->reassignToUser($device, $user->id);
        }

        return $device;
    }

    /**
     * Remove o registro de um dispositivo móvel de um usuário.
     */
    public function unregisterDevice(User $user, string $installationId): void
    {
        $this->deviceRepository->deleteForUser($user->id, $installationId);
    }

    /**
     * Lista as notificações do usuário com paginação.
     */
    public function paginateNotifications(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->notificationRepository->paginateForUser($user->id, $perPage);
    }

    /**
     * Marca uma notificação como lida.
     */
    public function markAsRead(User $user, string $notificationId): MobileNotification
    {
        $notification = $this->notificationRepository->findForUser($user->id, $notificationId);

        return $this->notificationRepository->markAsRead($notification);
    }

    /**
     * Envia notificações para uma lista de usuários.
     */
    public function notifyUsers(iterable $users, array $payload, ?string $dedupeKey = null): Collection
    {
        $notifications = collect();

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $resolvedDedupeKey = $dedupeKey ? "{$dedupeKey}:user:{$user->id}" : null;

            if ($resolvedDedupeKey) {
                $existing = $this->notificationRepository->findByDedupeKey($user->id, $resolvedDedupeKey);

                if ($existing) {
                    $notifications->push($existing);

                    continue;
                }
            }

            $notification = $this->notificationRepository->create([
                'user_id' => $user->id,
                'title' => (string) $payload['title'],
                'body' => (string) $payload['body'],
                'type' => (string) $payload['type'],
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => isset($payload['entity_id']) ? (string) $payload['entity_id'] : null,
                'tenant_slug' => tenant('slug'),
                'target_route' => $payload['target_route'] ?? null,
                'payload' => $payload['payload'] ?? [],
                'dedupe_key' => $resolvedDedupeKey,
                'sent_at' => now(),
            ]);

            $this->dispatchExpoPush($user, $notification);
            $notifications->push($notification);
        }

        return $notifications;
    }

    /**
     * Envia notificações para todos os usuários que possuem uma determinada permissão.
     */
    public function notifyUsersWithPermission(
        string $permission,
        array $payload,
        ?User $exclude = null,
        ?string $dedupeKey = null
    ): Collection {
        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission))
            ->when($exclude, fn (Collection $users) => $users->reject(fn (User $user) => $user->is($exclude)))
            ->values();

        return $this->notifyUsers($users, $payload, $dedupeKey);
    }

    /**
     * Envia notificações para todos os usuários do sistema.
     */
    public function notifyAllUsers(array $payload, ?User $exclude = null, ?string $dedupeKey = null): Collection
    {
        $users = $this->userRepository->getAllExcept($exclude?->getKey());

        return $this->notifyUsers($users, $payload, $dedupeKey);
    }

    /**
     * Notifica sobre etapas de legalização atrasadas no tenant atual.
     */
    public function notifyOverdueLegalizacaoEtapasForCurrentTenant(): int
    {
        $today = now()->startOfDay();
        $tenantSlug = (string) tenant('slug');

        $overdue = $this->legalizacaoEtapaRepository->findOverdue(
            [
                LegalizacaoEtapaStatus::CONCLUIDA->value,
                LegalizacaoEtapaStatus::BLOQUEADA->value,
            ],
            $today,
        );

        $count = 0;

        foreach ($overdue as $etapa) {
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
                    'tenant_slug' => $tenantSlug,
                ],
            ];

            if ($etapa->responsavel) {
                $this->notifyUsers([$etapa->responsavel], $payload, $dedupeKey);
                $count++;

                continue;
            }

            $this->notifyUsersWithPermission(
                (string) $this->permissions->forModel(LegalizacaoEtapa::class, 'update'),
                $payload,
                null,
                $dedupeKey,
            );
            $count++;
        }

        return $count;
    }

    /**
     * Realiza o envio técnico das notificações via API do Expo.
     */
    protected function dispatchExpoPush(User $user, MobileNotification $notification): void
    {
        $tokens = $this->deviceRepository->getTokensForUser($user->id);

        if ($tokens->isEmpty()) {
            return;
        }

        $endpoint = (string) config('services.expo.push_url', 'https://exp.host/--/api/v2/push/send');
        $accessToken = (string) config('services.expo.access_token', '');

        $messages = [];
        foreach ($tokens as $token) {
            $messages[] = [
                'to' => $token,
                'sound' => 'default',
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => [
                    'type' => $notification->type,
                    'entity_id' => $notification->entity_id,
                    'tenant_slug' => $notification->tenant_slug,
                    'target_route' => $notification->target_route,
                ],
            ];
        }

        try {
            $request = Http::timeout(10)
                ->acceptJson()
                ->asJson();

            if ($accessToken !== '') {
                $request = $request->withToken($accessToken);
            }

            $response = $request->post($endpoint, $messages);

            if (! $response->successful()) {
                $this->notificationRepository->recordDeliveryError($notification, $response->body());

                Log::warning('Expo push delivery failed', [
                    'notification_id' => $notification->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            $this->notificationRepository->recordDeliveryError($notification, $exception->getMessage());

            Log::warning('Expo push dispatch exception', [
                'notification_id' => $notification->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
