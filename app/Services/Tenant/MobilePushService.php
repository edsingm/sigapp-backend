<?php

namespace App\Services\Tenant;

use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\MobileDeviceInstallation;
use App\Models\Tenant\MobileNotification;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobilePushService
{
    public function registerDevice(User $user, array $attributes): MobileDeviceInstallation
    {
        $device = MobileDeviceInstallation::query()->updateOrCreate(
            ['installation_id' => (string) $attributes['installation_id']],
            [
                'user_id' => $user->id,
                'platform' => (string) ($attributes['platform'] ?? 'ios'),
                'device_name' => $attributes['device_name'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'expo_push_token' => $attributes['expo_push_token'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        if ($device->user_id !== $user->id) {
            $device->forceFill(['user_id' => $user->id])->save();
        }

        return $device->fresh();
    }

    public function unregisterDevice(User $user, string $installationId): void
    {
        MobileDeviceInstallation::query()
            ->where('user_id', $user->id)
            ->where('installation_id', $installationId)
            ->delete();
    }

    public function paginateNotifications(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return MobileNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function markAsRead(User $user, string $notificationId): MobileNotification
    {
        $notification = MobileNotification::query()
            ->where('user_id', $user->id)
            ->findOrFail($notificationId);

        if (!$notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification->fresh();
    }

    public function notifyUsers(iterable $users, array $payload, ?string $dedupeKey = null): Collection
    {
        $notifications = collect();

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $resolvedDedupeKey = $dedupeKey ? "{$dedupeKey}:user:{$user->id}" : null;

            if ($resolvedDedupeKey) {
                $existing = MobileNotification::query()
                    ->where('user_id', $user->id)
                    ->where('dedupe_key', $resolvedDedupeKey)
                    ->first();

                if ($existing) {
                    $notifications->push($existing);
                    continue;
                }
            }

            $notification = MobileNotification::query()->create([
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

    public function notifyUsersWithPermission(
        string $permission,
        array $payload,
        ?User $exclude = null,
        ?string $dedupeKey = null
    ): Collection {
        $users = User::query()
            ->where(function (Builder $query) use ($permission) {
                $query->whereHas('permissions', fn (Builder $builder) => $builder->where('name', $permission))
                    ->orWhereHas('roles.permissions', fn (Builder $builder) => $builder->where('name', $permission))
                    ->orWhereHas('roles', fn (Builder $builder) => $builder->whereIn('name', ['ADMIN', 'SUPER_ADMIN', 'admin', 'super_admin']));
            })
            ->when($exclude, fn (Builder $query) => $query->whereKeyNot($exclude->getKey()))
            ->get();

        return $this->notifyUsers($users, $payload, $dedupeKey);
    }

    public function notifyAllUsers(array $payload, ?User $exclude = null, ?string $dedupeKey = null): Collection
    {
        $users = User::query()
            ->when($exclude, fn (Builder $query) => $query->whereKeyNot($exclude->getKey()))
            ->get();

        return $this->notifyUsers($users, $payload, $dedupeKey);
    }

    public function notifyOverdueLegalizacaoEtapasForCurrentTenant(): int
    {
        $today = now()->startOfDay();
        $tenantSlug = (string) tenant('slug');

        $overdue = LegalizacaoEtapa::query()
            ->with(['legalizacao.terreno', 'responsavel'])
            ->whereNotIn('status', [
                LegalizacaoEtapa::STATUS_CONCLUIDA,
                LegalizacaoEtapa::STATUS_BLOQUEADA,
            ])
            ->where(function (Builder $query) use ($today) {
                $query->whereDate('data_prevista', '<', $today)
                    ->orWhereDate('fim_planejado', '<', $today);
            })
            ->get();

        $count = 0;

        foreach ($overdue as $etapa) {
            $dedupeKey = sprintf(
                'legalizacao-etapa-atrasada:%s:%s',
                $etapa->id,
                $today->format('Y-m-d')
            );

            $payload = [
                'title' => 'Etapa de legalização atrasada',
                'body' => sprintf(
                    '%s em %s está atrasada.',
                    $etapa->nome,
                    $etapa->legalizacao?->terreno?->nome ?? 'terreno sem nome'
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
                'update legalizacao etapas',
                $payload,
                null,
                $dedupeKey
            );
            $count++;
        }

        return $count;
    }

    protected function dispatchExpoPush(User $user, MobileNotification $notification): void
    {
        $tokens = MobileDeviceInstallation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('expo_push_token')
            ->pluck('expo_push_token')
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $endpoint = (string) config('services.expo.push_url', 'https://exp.host/--/api/v2/push/send');
        $accessToken = (string) config('services.expo.access_token', '');

        $messages = $tokens->map(fn (string $token) => [
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
        ])->all();

        try {
            $request = Http::timeout(10)
                ->acceptJson()
                ->asJson();

            if ($accessToken !== '') {
                $request = $request->withToken($accessToken);
            }

            $response = $request->post($endpoint, $messages);

            if (!$response->successful()) {
                $notification->forceFill([
                    'delivery_error' => $response->body(),
                ])->save();

                Log::warning('Expo push delivery failed', [
                    'notification_id' => $notification->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            $notification->forceFill([
                'delivery_error' => $exception->getMessage(),
            ])->save();

            Log::warning('Expo push dispatch exception', [
                'notification_id' => $notification->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
