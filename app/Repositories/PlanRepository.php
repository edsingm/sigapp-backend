<?php

namespace App\Repositories;

use App\Enums\Common\EntitlementType;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PlanRepository implements PlanRepositoryInterface
{
    private const CACHE_TTL = 3600;

    public function all(): Collection
    {
        return Plan::ordered()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Plan::ordered()->paginate($perPage);
    }

    public function findById(int $id): ?Plan
    {
        return Plan::find($id);
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->refresh();
    }

    public function delete(Plan $plan): void
    {
        $this->invalidateMatrixCache($plan->id);
        $plan->delete();
    }

    /**
     * @return array{features: array<string, mixed>, limits: array<string, int>}
     */
    public function getMatrix(int $planId): array
    {
        return Cache::tags(['plan_matrix', "plan_matrix_{$planId}"])
            ->remember("plan_matrix_{$planId}", self::CACHE_TTL, function () use ($planId): array {
                return $this->buildMatrix($planId);
            });
    }

    /**
     * @param  array<int, mixed>  $entitlements  [entitlement_id => value, ...]
     */
    public function syncEntitlements(Plan $plan, array $entitlements): void
    {
        $sync = [];

        foreach ($entitlements as $entitlementId => $value) {
            $sync[(int) $entitlementId] = ['value' => json_encode($value)];
        }

        $plan->entitlements()->sync($sync);
        $this->invalidateMatrixCache($plan->id);
    }

    public function invalidateMatrixCache(int $planId): void
    {
        Cache::tags(["plan_matrix_{$planId}"])->flush();
    }

    /**
     * @return array{features: array<string, mixed>, limits: array<string, int>}
     */
    private function buildMatrix(int $planId): array
    {
        $entitlements = Entitlement::query()
            ->join('plan_entitlements', 'entitlements.id', '=', 'plan_entitlements.entitlement_id')
            ->where('plan_entitlements.plan_id', $planId)
            ->select('entitlements.key', 'entitlements.type', 'plan_entitlements.value')
            ->get();

        $features = [];
        $limits = [];

        foreach ($entitlements as $row) {
            $value = json_decode((string) $row->value, true);

            if ($row->type === EntitlementType::FEATURE->value || $row->type === EntitlementType::FEATURE) {
                data_set($features, $row->key, (bool) $value);
            } else {
                $limits[$row->key] = (int) $value;
            }
        }

        return ['features' => $features, 'limits' => $limits];
    }
}
