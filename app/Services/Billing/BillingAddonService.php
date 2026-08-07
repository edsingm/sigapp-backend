<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Common\BillingAddonType;
use App\Models\Central\BillingAddon;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class BillingAddonService
{
    public function __construct(
        private readonly BillingAddonRepositoryInterface $repository,
        private readonly BillingAddonDefinitionService $definitionService,
    ) {}

    /** @return Collection<int, BillingAddon> */
    public function list(bool $activeOnly = false): Collection
    {
        return $this->repository->all($activeOnly);
    }

    public function findOrFail(int $id): BillingAddon
    {
        $addon = $this->repository->findById($id);

        if ($addon === null) {
            throw new InvalidArgumentException("Add-on #{$id} não encontrado.");
        }

        return $addon;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): BillingAddon
    {
        $type = BillingAddonType::from((string) $data['type']);
        $data['type'] = $type->value;
        $data['definition'] = $this->definitionService->normalize($type, (array) $data['definition']);
        $data['currency'] = strtolower((string) ($data['currency'] ?? config('cashier.currency', 'brl')));
        $data['billing_interval'] = 'month';

        return $this->repository->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): BillingAddon
    {
        $addon = $this->findOrFail($id);
        $hasSubscriptions = $this->repository->hasSubscriptions($addon);

        if ($hasSubscriptions) {
            $immutableFields = ['slug', 'type', 'definition', 'stripe_price_id'];
            $changedImmutableField = collect($immutableFields)->contains(
                fn (string $field): bool => array_key_exists($field, $data)
                    && $data[$field] !== $addon->getAttribute($field)
            );

            if ($changedImmutableField) {
                throw new InvalidArgumentException(
                    'Não altere SKU, preço ou concessões de um add-on contratado; crie um novo versionamento.'
                );
            }
        }

        $type = isset($data['type'])
            ? BillingAddonType::from((string) $data['type'])
            : $addon->type;
        $definition = array_key_exists('definition', $data)
            ? (array) $data['definition']
            : (array) $addon->definition;

        if (! $hasSubscriptions || array_key_exists('definition', $data) || array_key_exists('type', $data)) {
            $data['type'] = $type->value;
            $data['definition'] = $this->definitionService->normalize($type, $definition);
        }

        return $this->repository->update($addon, $data);
    }

    public function delete(int $id): void
    {
        $addon = $this->findOrFail($id);

        if ($this->repository->hasSubscriptions($addon)) {
            throw new InvalidArgumentException(
                'Não é possível excluir um add-on com assinaturas; desative-o ou crie uma nova versão.'
            );
        }

        $this->repository->delete($addon);
    }
}
