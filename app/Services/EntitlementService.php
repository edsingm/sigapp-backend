<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Common\EntitlementScope;
use App\Enums\Common\EntitlementType;
use App\Models\Central\Entitlement;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Support\EntitlementCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EntitlementService
{
    public function __construct(
        private readonly EntitlementRepositoryInterface $entitlementRepository,
        private readonly PlanRepositoryInterface $planRepository,
        private readonly EntitlementValueService $valueService,
    ) {}

    public function list(): Collection
    {
        return $this->entitlementRepository->all();
    }

    public function create(array $data): Entitlement
    {
        if ($this->entitlementRepository->findByKey($data['key']) !== null) {
            throw new InvalidArgumentException("Entitlement com key [{$data['key']}] já existe.");
        }

        $type = EntitlementType::from((string) $data['type']);
        $defaultScope = $type === EntitlementType::LIMIT
            ? EntitlementScope::INTERNAL
            : EntitlementCatalog::scopeForFeature((string) $data['key']);
        $data['scope'] = $this->valueService
            ->validateScope($type, $data['scope'] ?? $defaultScope)
            ->value;
        $data['default_value'] = $this->valueService->normalize(
            $type,
            (string) $data['key'],
            $data['default_value'] ?? null,
        );

        return DB::transaction(
            fn (): Entitlement => $this->entitlementRepository->create($data)
        );
    }

    public function update(int $id, array $data): Entitlement
    {
        $entitlement = $this->entitlementRepository->findById($id);

        if ($entitlement === null) {
            throw new InvalidArgumentException("Entitlement #{$id} não encontrado.");
        }

        if (isset($data['key']) && $data['key'] !== $entitlement->key) {
            $existing = $this->entitlementRepository->findByKey($data['key']);

            if ($existing !== null) {
                throw new InvalidArgumentException("Entitlement com key [{$data['key']}] já existe.");
            }
        }

        $type = isset($data['type'])
            ? EntitlementType::from((string) $data['type'])
            : $entitlement->type;

        if ($type !== $entitlement->type && $this->entitlementRepository->hasLinks($entitlement)) {
            throw new InvalidArgumentException('Não é possível alterar o tipo de um entitlement com vínculos.');
        }

        $key = (string) ($data['key'] ?? $entitlement->key);
        if (array_key_exists('scope', $data) || $type !== $entitlement->type || $key !== $entitlement->key) {
            $defaultScope = $type === EntitlementType::LIMIT
                ? EntitlementScope::INTERNAL
                : EntitlementCatalog::scopeForFeature($key);
            $scope = $data['scope'] ?? $defaultScope;
            $data['scope'] = $this->valueService->validateScope($type, $scope)->value;
        }

        if (array_key_exists('default_value', $data) || $type !== $entitlement->type || $key !== $entitlement->key) {
            $data['default_value'] = $this->valueService->normalize(
                $type,
                $key,
                $data['default_value'] ?? $entitlement->default_value,
            );
        }

        $planIds = $this->entitlementRepository->linkedPlanIds($entitlement);

        $updated = DB::transaction(
            fn (): Entitlement => $this->entitlementRepository->update($entitlement, $data),
        );
        $this->invalidatePlans($planIds);

        return $updated;
    }

    public function delete(int $id): void
    {
        $entitlement = $this->entitlementRepository->findById($id);

        if ($entitlement === null) {
            throw new InvalidArgumentException("Entitlement #{$id} não encontrado.");
        }

        $planIds = $this->entitlementRepository->linkedPlanIds($entitlement);

        DB::transaction(
            fn () => $this->entitlementRepository->delete($entitlement),
        );
        $this->invalidatePlans($planIds);
    }

    public function findOrFail(int $id): Entitlement
    {
        $entitlement = $this->entitlementRepository->findById($id);

        if ($entitlement === null) {
            throw new InvalidArgumentException("Entitlement #{$id} não encontrado.");
        }

        return $entitlement;
    }

    /** @param array<int, int> $planIds */
    private function invalidatePlans(array $planIds): void
    {
        foreach ($planIds as $planId) {
            $this->planRepository->invalidateMatrixCache($planId);
        }
    }
}
