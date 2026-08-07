<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Central\BillingAddon;
use Illuminate\Database\Eloquent\Collection;

interface BillingAddonRepositoryInterface
{
    /** @return Collection<int, BillingAddon> */
    public function all(bool $activeOnly = false): Collection;

    public function findById(int $id): ?BillingAddon;

    public function findBySlug(string $slug): ?BillingAddon;

    public function findByStripePriceId(string $stripePriceId): ?BillingAddon;

    public function create(array $data): BillingAddon;

    public function update(BillingAddon $addon, array $data): BillingAddon;

    public function delete(BillingAddon $addon): void;

    public function hasSubscriptions(BillingAddon $addon): bool;
}
