<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use App\Notifications\NotificationCatalog;
use App\Repositories\Contracts\NotificationPreferenceRepositoryInterface;
use Illuminate\Support\Carbon;

class NotificationPreferenceService
{
    public const DIGEST_INSTANT = 'instant';

    public const DIGEST_DAILY = 'daily';

    public const DIGEST_WEEKLY = 'weekly';

    public const DIGEST_FREQUENCIES = [
        self::DIGEST_INSTANT,
        self::DIGEST_DAILY,
        self::DIGEST_WEEKLY,
    ];

    /**
     * Cache em memória das preferências por usuário durante o request.
     *
     * @var array<int, array<string, bool>>
     */
    private array $cache = [];

    public function __construct(
        private readonly NotificationPreferenceRepositoryInterface $repository,
    ) {}

    /**
     * Indica se o usuário deve receber a categoria pelo canal informado.
     */
    public function isEnabled(User $user, string $category, string $channel): bool
    {
        if (! NotificationCatalog::isChannelAvailable($category, $channel)) {
            return false;
        }

        $prefs = $this->cache[$user->id] ??= $this->repository->mapForUser($user->id);

        return $prefs["{$category}|{$channel}"] ?? NotificationCatalog::defaultEnabled($category, $channel);
    }

    /**
     * Matriz completa de categorias x canais com o estado atual do usuário.
     *
     * @return array<int, array{key: string, label: string, channels: array<int, array{channel: string, enabled: bool}>}>
     */
    public function matrixForUser(User $user): array
    {
        $matrix = [];

        foreach (NotificationCatalog::categories() as $key => $meta) {
            $channels = [];

            foreach ($meta['channels'] as $channel) {
                $channels[] = [
                    'channel' => $channel,
                    'enabled' => $this->isEnabled($user, $key, $channel),
                ];
            }

            $matrix[] = [
                'key' => $key,
                'label' => $meta['label'],
                'channels' => $channels,
            ];
        }

        return $matrix;
    }

    /**
     * Persiste as preferências enviadas, ignorando combinações inválidas.
     *
     * @param  array<int, array{category: string, channel: string, enabled: bool}>  $preferences
     */
    public function updateForUser(User $user, array $preferences): void
    {
        foreach ($preferences as $preference) {
            $category = $preference['category'];
            $channel = $preference['channel'];

            if (! NotificationCatalog::isChannelAvailable($category, $channel)) {
                continue;
            }

            $this->repository->upsert($user->id, $category, $channel, (bool) $preference['enabled']);
        }

        unset($this->cache[$user->id]);
    }

    /**
     * Frequência de resumo de e-mail do usuário (instant | daily | weekly).
     */
    public function emailDigestFrequency(User $user): string
    {
        $value = (string) ($user->email_digest_frequency ?? self::DIGEST_INSTANT);

        return in_array($value, self::DIGEST_FREQUENCIES, true) ? $value : self::DIGEST_INSTANT;
    }

    /**
     * Indica se o momento atual está dentro da janela de silêncio do usuário.
     */
    public function isWithinQuietHours(User $user, ?Carbon $moment = null): bool
    {
        $start = $this->parseTime($user->quiet_hours_start);
        $end = $this->parseTime($user->quiet_hours_end);

        if ($start === null || $end === null || $start === $end) {
            return false;
        }

        $now = ($moment ?? now())->format('H:i');
        $nowMinutes = $this->parseTime($now);

        // Janela que cruza a meia-noite (ex.: 22:00–07:00).
        if ($start > $end) {
            return $nowMinutes >= $start || $nowMinutes < $end;
        }

        return $nowMinutes >= $start && $nowMinutes < $end;
    }

    /**
     * Configurações globais de notificação do usuário.
     *
     * @return array{quiet_hours_start: ?string, quiet_hours_end: ?string, email_digest_frequency: string}
     */
    public function settingsForUser(User $user): array
    {
        return [
            'quiet_hours_start' => $user->quiet_hours_start,
            'quiet_hours_end' => $user->quiet_hours_end,
            'email_digest_frequency' => $this->emailDigestFrequency($user),
        ];
    }

    /**
     * Atualiza as configurações globais de notificação do usuário.
     *
     * @param  array{quiet_hours_start?: ?string, quiet_hours_end?: ?string, email_digest_frequency?: string}  $settings
     */
    public function updateSettingsForUser(User $user, array $settings): void
    {
        if (array_key_exists('quiet_hours_start', $settings)) {
            $user->quiet_hours_start = $settings['quiet_hours_start'] ?: null;
        }

        if (array_key_exists('quiet_hours_end', $settings)) {
            $user->quiet_hours_end = $settings['quiet_hours_end'] ?: null;
        }

        if (array_key_exists('email_digest_frequency', $settings)) {
            $frequency = (string) $settings['email_digest_frequency'];
            $user->email_digest_frequency = in_array($frequency, self::DIGEST_FREQUENCIES, true)
                ? $frequency
                : self::DIGEST_INSTANT;
        }

        $user->save();
    }

    /**
     * Converte "HH:MM" em minutos desde a meia-noite; null se inválido.
     */
    private function parseTime(?string $value): ?int
    {
        if ($value === null || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
