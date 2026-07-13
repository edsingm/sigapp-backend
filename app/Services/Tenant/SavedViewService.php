<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\SavedView;
use App\Models\Tenant\User;
use App\Repositories\Tenant\SavedViewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SavedViewService
{
    public const RESOURCES = ['workspace', 'terrenos', 'viabilidades', 'comite', 'negociacoes', 'legalizacoes', 'projetos'];

    public function __construct(
        private readonly SavedViewRepository $repository,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $this->assertResource($filters['resource'] ?? null);

        return $this->repository->paginateForUser($user->id, $filters);
    }

    public function find(User $user, int $id): SavedView
    {
        return $this->repository->findForUserOrFail($user->id, $id);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): SavedView
    {
        $this->assertResource($data['resource'] ?? null);
        $sharedWith = array_values(array_unique(array_map('intval', (array) ($data['shared_with_user_ids'] ?? []))));
        $data = $this->normalize($data);

        return DB::transaction(function () use ($user, $data, $sharedWith): SavedView {
            if ($data['is_default']) {
                $this->repository->clearDefaults($user->id, $data['resource']);
            }

            $view = $this->repository->create([...$data, 'owner_id' => $user->id]);
            if (($data['scope'] ?? 'private') === 'shared' && $sharedWith !== []) {
                $view->sharedWith()->sync($sharedWith);
            }

            return $this->find($user, $view->id);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, SavedView $view, array $data): SavedView
    {
        if ($view->owner_id !== $user->id) {
            throw new RuntimeException('Somente o proprietário pode editar esta visão.');
        }
        if (isset($data['resource'])) {
            $this->assertResource($data['resource']);
        }
        $hasSharedWith = array_key_exists('shared_with_user_ids', $data);
        $sharedWith = array_values(array_unique(array_map('intval', (array) ($data['shared_with_user_ids'] ?? []))));
        $data = $this->normalize($data, partial: true);

        return DB::transaction(function () use ($user, $view, $data, $hasSharedWith, $sharedWith): SavedView {
            $resource = (string) ($data['resource'] ?? $view->resource);
            if (($data['is_default'] ?? false) === true) {
                $this->repository->clearDefaults($user->id, $resource);
            }
            $updated = $this->repository->update($view, $data);
            if ($hasSharedWith) {
                $updated->sharedWith()->sync($sharedWith);
            }

            return $this->find($user, $updated->id);
        });
    }

    public function delete(User $user, SavedView $view): void
    {
        if ($view->owner_id !== $user->id) {
            throw new RuntimeException('Somente o proprietário pode excluir esta visão.');
        }
        $this->repository->delete($view);
    }

    public function setDefault(User $user, SavedView $view): SavedView
    {
        if ($view->owner_id !== $user->id) {
            throw new RuntimeException('Somente o proprietário pode definir a visão padrão.');
        }

        return DB::transaction(function () use ($user, $view): SavedView {
            $this->repository->clearDefaults($user->id, $view->resource);

            return $this->repository->update($view, ['is_default' => true]);
        });
    }

    private function assertResource(mixed $resource): void
    {
        if (! is_string($resource) || ! in_array($resource, self::RESOURCES, true)) {
            throw new RuntimeException('Recurso de visão salvo inválido.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        foreach (['filters', 'columns', 'sort'] as $key) {
            if (array_key_exists($key, $data) && ! is_array($data[$key])) {
                throw new RuntimeException("{$key} deve ser um objeto ou lista válido.");
            }
        }
        $data['filters'] = $data['filters'] ?? ($partial ? null : []);
        $data['columns'] = $data['columns'] ?? ($partial ? null : []);
        $data['sort'] = $data['sort'] ?? ($partial ? null : []);
        $data['scope'] = $data['scope'] ?? ($partial ? null : 'private');
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        unset($data['shared_with_user_ids']);

        return array_filter($data, fn (mixed $value): bool => $value !== null || ! $partial);
    }
}
