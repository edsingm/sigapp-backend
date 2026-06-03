<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoInfos;
use App\Repositories\Contracts\TerrenoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TerrenoRepository implements TerrenoRepositoryInterface
{
    public function findById(int|string $id): ?Terreno
    {
        return Terreno::query()->find($id);
    }

    public function findOrFail(int|string $id): Terreno
    {
        return Terreno::query()->findOrFail($id);
    }

    public function findInfoOrFail(int|string $id): TerrenoInfos
    {
        return TerrenoInfos::query()->findOrFail($id);
    }

    /**
     * @param  array{search?: string|null, per_page?: int, page?: int}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Terreno::query();

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where('nome', 'like', "%{$search}%");
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function create(array $data): Terreno
    {
        return Terreno::create($data);
    }

    public function update(Terreno $terreno, array $data): Terreno
    {
        $terreno->update($data);

        return $terreno->refresh();
    }

    public function delete(Terreno $terreno): void
    {
        $terreno->delete();
    }

    /**
     * @return Collection<int, Terreno>
     */
    public function listForSelect(): Collection
    {
        return Terreno::query()
            ->select('id', 'nome')
            ->orderBy('nome')
            ->get();
    }

    public function createInfo(Terreno $terreno, array $data): TerrenoInfos
    {
        return $terreno->informacoes()->create($data);
    }

    /**
     * @return Collection<int, TerrenoInfos>
     */
    public function listInfos(Terreno $terreno): Collection
    {
        return $terreno->informacoes()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->get();
    }

    public function updateInfo(TerrenoInfos $info, array $data): TerrenoInfos
    {
        $info->update($data);

        return $info->refresh();
    }

    public function deleteInfo(TerrenoInfos $info): void
    {
        $info->delete();
    }

    public function loadDetailRelations(Terreno $terreno): Terreno
    {
        return $terreno->fresh([
            'responsavel',
            'corretorExterno',
            'regional',
            'cidade',
            'createdBy',
            'updatedBy',
            'proprietarios',
            'contatos',
            'documentos',
            'terrenoProdutos.produto',
            'viabilidades.createdBy',
            'viabilidades.approvalDecidedBy',
            'viabilidadeAtual.createdBy',
            'viabilidadeAtual.approvalDecidedBy',
            'viabilidadeAtual.secoes',
            'viabilidadeAtual.aprovacoes.user',
            'informacoes.user',
            'comiteAtual.viabilidade',
            'comiteAtual.pareceresDepartamento',
            'comiteAtual.pendencias',
            'negociacaoAtual.eventos',
            'contratoAtual.partes',
            'legalizacao.etapas',
            'legalizacao.pendencias',
            'tasks.assignedUser',
            'statusHistories',
            'activities',
        ]);
    }

    public function loadWorkflowRelations(Terreno $terreno): Terreno
    {
        return $terreno->fresh([
            'proprietarios',
            'contatos',
            'terrenoProdutos',
            'viabilidadeAtual',
            'comiteAtual.pareceresDepartamento',
            'comiteAtual.pendencias',
            'contratoAtual.partes',
            'legalizacao.etapas',
            'legalizacao.pendencias',
        ]);
    }
}
