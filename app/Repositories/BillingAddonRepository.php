<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\BillingAddon;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BillingAddonRepository implements BillingAddonRepositoryInterface
{
    /** @return Collection<int, BillingAddon> */
    public function all(bool $activeOnly = false): Collection
    {
        $query = BillingAddon::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($activeOnly) {
            $query->active();
        }

        /** @var Collection<int, BillingAddon> $addons */
        $addons = $query->get();

        return $addons;
    }

    public function findById(int $id): ?BillingAddon
    {
        return BillingAddon::query()->find($id);
    }

    public function findBySlug(string $slug): ?BillingAddon
    {
        return BillingAddon::query()->where('slug', $slug)->first();
    }

    public function findByStripePriceId(string $stripePriceId): ?BillingAddon
    {
        return BillingAddon::query()->where('stripe_price_id', $stripePriceId)->first();
    }

    public function create(array $data): BillingAddon
    {
        return BillingAddon::query()->create($data);
    }

    public function update(BillingAddon $addon, array $data): BillingAddon
    {
        $addon->update($data);

        return $addon->refresh();
    }

    public function delete(BillingAddon $addon): void
    {
        $addon->delete();
    }

    public function hasSubscriptions(BillingAddon $addon): bool
    {
        return $addon->subscriptions()->exists() || $addon->purchases()->exists();
    }
}
