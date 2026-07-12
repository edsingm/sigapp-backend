<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use RuntimeException;

class UserPreferencesService
{
    private const REFERENCE_TYPES = ['terreno', 'viabilidade', 'comite', 'negociacao', 'legalizacao', 'projeto', 'documento', 'relatorio'];

    public function __construct(
        private readonly NotificationPreferenceService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function get(User $user): array
    {
        return [
            'theme' => $user->theme ?? 'system',
            'locale' => $user->locale ?? 'pt-br',
            'timezone' => $user->timezone ?? 'America/Sao_Paulo',
            'density' => $user->density ?? 'comfortable',
            'dashboard_layout' => $user->dashboard_layout,
            'favorites' => $this->references($user->favorites),
            'recent' => $this->references($user->recent),
            'notification_preferences' => $this->notifications->matrixForUser($user),
            'notification_settings' => $this->notifications->settingsForUser($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(User $user, array $data): array
    {
        foreach (['favorites', 'recent'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->normalizeReferences($data[$key]);
            }
        }

        $user->fill(array_filter([
            'theme' => $data['theme'] ?? null,
            'locale' => $data['locale'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'density' => $data['density'] ?? null,
            'dashboard_layout' => $data['dashboard_layout'] ?? null,
            'favorites' => $data['favorites'] ?? null,
            'recent' => $data['recent'] ?? null,
        ], fn (mixed $value): bool => $value !== null));
        $user->save();

        if (array_key_exists('notification_preferences', $data)) {
            $flat = [];
            foreach ((array) $data['notification_preferences'] as $category) {
                foreach ((array) ($category['channels'] ?? []) as $channel) {
                    $flat[] = [
                        'category' => $category['key'] ?? '',
                        'channel' => $channel['channel'] ?? '',
                        'enabled' => (bool) ($channel['enabled'] ?? false),
                    ];
                }
            }
            $this->notifications->updateForUser($user, $flat);
        }
        if (array_key_exists('notification_settings', $data) && is_array($data['notification_settings'])) {
            $this->notifications->updateSettingsForUser($user, $data['notification_settings']);
        }

        return $this->get($user->fresh() ?? $user);
    }

    /**
     * @return array<int, array{id: int|string, type: string}>
     */
    private function normalizeReferences(mixed $references): array
    {
        if (! is_array($references)) {
            throw new RuntimeException('Referências de preferência inválidas.');
        }
        $result = [];
        $seen = [];
        foreach (array_slice($references, 0, 50) as $reference) {
            if (! is_array($reference) || ! isset($reference['id'], $reference['type']) || ! is_string($reference['type'])) {
                throw new RuntimeException('Cada referência deve informar id e type.');
            }
            if (! in_array($reference['type'], self::REFERENCE_TYPES, true)) {
                throw new RuntimeException('Tipo de referência não permitido.');
            }
            $key = $reference['type'].':'.(string) $reference['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = ['id' => $reference['id'], 'type' => $reference['type']];
        }

        return $result;
    }

    /**
     * @return array<int, array{id: int|string, type: string, label: string|null, href: string|null}>
     */
    private function references(mixed $references): array
    {
        return array_map(fn (array $reference): array => [
            'id' => $reference['id'],
            'type' => $reference['type'],
            'label' => null,
            'href' => null,
        ], is_array($references) ? $references : []);
    }
}
