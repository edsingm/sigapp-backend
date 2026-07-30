<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UsageMetricsRepositoryInterface
{
    public function userCount(): int;

    public function terrenoCount(): int;

    public function produtoCount(): int;

    public function storageUsedBytes(): int;

    /**
     * @return array<string, array{disk: string, path: string, size: int}>
     */
    public function storageObjects(): array;
}
