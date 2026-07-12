<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\SavedView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SavedViewRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateForUser(int $userId, array $filters): LengthAwarePaginator
    {
        return SavedView::query()
            ->with(['owner', 'sharedWith'])
            ->where(function ($query) use ($userId): void {
                $query->where('owner_id', $userId)
                    ->orWhere(function ($shared) use ($userId): void {
                        $shared->where('scope', 'shared')->whereHas('sharedWith', fn ($users) => $users->whereKey($userId));
                    });
            })
            ->when($filters['resource'] ?? null, fn ($query, $resource) => $query->where('resource', $resource))
            ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function findForUserOrFail(int $userId, int $id): SavedView
    {
        return SavedView::query()
            ->with(['owner', 'sharedWith'])
            ->whereKey($id)
            ->where(function ($query) use ($userId): void {
                $query->where('owner_id', $userId)
                    ->orWhere(fn ($shared) => $shared->where('scope', 'shared')->whereHas('sharedWith', fn ($users) => $users->whereKey($userId)));
            })
            ->firstOrFail();
    }

    public function create(array $data): SavedView
    {
        return SavedView::create($data);
    }

    public function update(SavedView $view, array $data): SavedView
    {
        $view->update($data);

        return $view->fresh(['owner', 'sharedWith']) ?? throw new \RuntimeException('Visão salva não encontrada após atualização.');
    }

    public function delete(SavedView $view): void
    {
        $view->delete();
    }

    public function clearDefaults(int $ownerId, string $resource): void
    {
        SavedView::query()->where('owner_id', $ownerId)->where('resource', $resource)->update(['is_default' => false]);
    }
}
