<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface NotificationPreferenceRepositoryInterface
{
    /**
     * Retorna as preferências do usuário no formato ["category|channel" => bool].
     *
     * @return array<string, bool>
     */
    public function mapForUser(int $userId): array;

    public function upsert(int $userId, string $category, string $channel, bool $enabled): void;
}
