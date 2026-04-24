<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use App\Models\Tenant\ViabilidadeAprovacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ViabilidadeRepository
{
    /**
     * @var list<string>
     */
    private const DEFAULT_RELATIONS = [
        'terreno',
        'createdBy',
        'updatedBy',
        'approvalDecidedBy',
        'secoes',
        'aprovacoes.user',
    ];

    /**
     * @var list<string>
     */
    private const DRE_RELATIONS = [
        'terreno.cidade',
        'createdBy',
        'updatedBy',
        'approvalDecidedBy',
        'secoes',
        'aprovacoes.user',
    ];

    public function findOrFail(int|string $id): Viabilidade
    {
        return Viabilidade::query()->findOrFail($id);
    }

    public function findWithTrashedOrFail(int|string $id): Viabilidade
    {
        return Viabilidade::withTrashed()->findOrFail($id);
    }

    public function create(array $data): Viabilidade
    {
        return Viabilidade::query()->create($data);
    }

    public function update(Viabilidade $viabilidade, array $data): Viabilidade
    {
        $viabilidade->update($data);

        return $viabilidade->refresh();
    }

    public function delete(Viabilidade $viabilidade): bool
    {
        return (bool) $viabilidade->delete();
    }

    public function restore(Viabilidade $viabilidade): Viabilidade
    {
        $viabilidade->restore();

        return $viabilidade->refresh();
    }

    public function terrenoExists(int|string $id): bool
    {
        return Terreno::query()->whereKey($id)->exists();
    }

    public function findTerrenoOrFail(int|string $id): Terreno
    {
        return Terreno::query()->findOrFail($id);
    }

    public function nextVersionForTerreno(int $terrenoId): int
    {
        return ((int) Viabilidade::query()
            ->where('terreno_id', $terrenoId)
            ->max('version')) + 1;
    }

    public function clearCurrentForTerreno(int $terrenoId): void
    {
        Viabilidade::query()
            ->where('terreno_id', $terrenoId)
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    /**
     * @param  array{search?: string|null, terreno_id?: int|string|null, per_page?: int|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Viabilidade::query()->with(['terreno', 'createdBy', 'updatedBy']);

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->whereHas('terreno', function ($terrainQuery) use ($search): void {
                $terrainQuery->where('nome', 'like', "%{$search}%");
            });
        }

        $terrenoId = $filters['terreno_id'] ?? null;
        if ($terrenoId !== null && $terrenoId !== '') {
            $query->where('terreno_id', $terrenoId);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('version')
            ->paginate((int) ($filters['per_page'] ?? 10));
    }

    /**
     * @return Collection<int, Viabilidade>
     */
    public function listByTerreno(int $terrenoId): Collection
    {
        return Viabilidade::query()
            ->with(['terreno', 'createdBy', 'updatedBy', 'approvalDecidedBy'])
            ->where('terreno_id', $terrenoId)
            ->orderByDesc('version')
            ->orderByDesc('created_at')
            ->get();
    }

    public function latestByTerreno(int $terrenoId): ?Viabilidade
    {
        return Viabilidade::query()
            ->with(['terreno', 'createdBy', 'updatedBy', 'approvalDecidedBy'])
            ->where('terreno_id', $terrenoId)
            ->where('is_current', true)
            ->orderByDesc('version')
            ->orderByDesc('created_at')
            ->first();
    }

    public function createApproval(Viabilidade $viabilidade, ?int $userId, string $decision, ?string $comments): ViabilidadeAprovacao
    {
        return ViabilidadeAprovacao::query()->create([
            'viabilidade_id' => $viabilidade->id,
            'user_id' => $userId,
            'decision' => $decision,
            'comments' => $comments,
            'created_at' => now(),
        ]);
    }

    public function copySections(Viabilidade $source, Viabilidade $target): void
    {
        $source->loadMissing('secoes');

        foreach ($source->secoes as $secao) {
            $target->secoes()->create([
                'section_code' => $secao->section_code,
                'section_name' => $secao->section_name,
                'content_json' => $secao->content_json,
                'status' => $secao->status,
            ]);
        }
    }

    /**
     * @return Collection<int, Viabilidade>
     */
    public function forSelect(?int $terrenoId = null): Collection
    {
        return Viabilidade::query()
            ->with('terreno')
            ->when($terrenoId !== null, fn ($query) => $query->where('terreno_id', $terrenoId))
            ->orderByDesc('created_at')
            ->get();
    }

    public function loadDefaultRelations(Viabilidade $viabilidade): Viabilidade
    {
        return $this->load($viabilidade, self::DEFAULT_RELATIONS);
    }

    public function loadDreRelations(Viabilidade $viabilidade): Viabilidade
    {
        return $this->load($viabilidade, self::DRE_RELATIONS);
    }

    /**
     * @param  list<string>  $relations
     */
    public function load(Viabilidade $viabilidade, array $relations): Viabilidade
    {
        return $viabilidade->fresh($relations);
    }
}
