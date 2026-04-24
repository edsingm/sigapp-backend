<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProjetoStatus;
use App\Enums\WorkflowStatus;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\ProjetoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProjetoRepository implements ProjetoRepositoryInterface
{
    public function findById(int $id): ?Projeto
    {
        return Projeto::find($id);
    }

    public function findWithRelations(int $id): ?Projeto
    {
        return Projeto::with([
            'status',
            'terreno',
            'createdBy',
            'updatedBy',
            'departamentos' => fn ($q) => $q->select('departments.id', 'departments.name'),
        ])->find($id);
    }

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Projeto::with([
            'status',
            'terreno',
            'createdBy',
            'updatedBy',
        ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function listWithFilters(array $filters): LengthAwarePaginator
    {
        $query = Projeto::query()
            ->with([
                'responsavel',
                'terreno',
                'terreno.viabilidadeAtual.approvalDecidedBy',
                'terreno.comiteAtual',
                'terreno.negociacaoAtual',
                'terreno.contratoAtual',
                'terreno.legalizacao',
                'prontoParaRegistroPor',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('nome', 'like', "%{$search}%")
                    ->orWhereHas('terreno', function (Builder $q) use ($search) {
                        $q->where('nome', 'like', "%{$search}%")
                            ->orWhere('codigo', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['responsavel_id'])) {
            $query->where('responsavel_id', $filters['responsavel_id']);
        }

        if (! empty($filters['data_inicio'])) {
            $query->whereDate('created_at', '>=', $filters['data_inicio']);
        }

        if (! empty($filters['data_fim'])) {
            $query->whereDate('created_at', '<=', $filters['data_fim']);
        }

        return $query->latest('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function listTerrenosElegiveis(array $filters): LengthAwarePaginator
    {
        $query = Terreno::query()
            ->with([
                'viabilidadeAtual.aprovacoes.user',
                'comiteAtual',
                'negociacaoAtual',
                'contratoAtual',
                'regional',
                'cidade',
                'responsavel',
                'projeto',
                'legalizacao.etapas',
            ])
            ->where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)
            ->whereDoesntHave('projeto', function (Builder $q) {
                $q->whereIn('status', [
                    ProjetoStatus::EM_VIABILIDADE,
                    ProjetoStatus::EM_LEGALIZACAO,
                ]);
            });

        if (! empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('nome', 'like', "%{$filters['search']}%")
                    ->orWhere('codigo', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('updated_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Projeto
    {
        return Projeto::create($data);
    }

    public function findTerrenoElegivel(int $terrenoId): Terreno
    {
        return Terreno::query()
            ->with(['legalizacao'])
            ->findOrFail($terrenoId);
    }

    public function existsActiveProjetoForTerreno(int $terrenoId): bool
    {
        return Projeto::query()
            ->where('terreno_id', $terrenoId)
            ->whereIn('status', [
                ProjetoStatus::EM_VIABILIDADE,
                ProjetoStatus::EM_LEGALIZACAO,
            ])
            ->exists();
    }

    public function findWithFullRelations(int $id): ?Projeto
    {
        return Projeto::query()
            ->with([
                'responsavel',
                'createdBy',
                'updatedBy',
                'prontoParaRegistroPor',
                'terreno',
                'terreno.cidade',
                'terreno.responsavel',
                'terreno.proprietarios',
                'terreno.contatos',
                'terreno.informacoes',
                'terreno.viabilidadeAtual.createdBy',
                'terreno.viabilidadeAtual.approvalDecidedBy',
                'terreno.viabilidadeAtual.secoes',
                'terreno.viabilidadeAtual.aprovacoes.user',
                'terreno.comiteAtual.pareceresDepartamento',
                'terreno.comiteAtual.pendencias',
                'terreno.negociacaoAtual.eventos',
                'terreno.contratoAtual.negociacao',
                'terreno.contratoAtual.partes',
                'terreno.legalizacao.terreno',
                'terreno.legalizacao.responsavel',
                'terreno.legalizacao.etapas',
                'terreno.legalizacao.pendencias',
                'terreno.tasks.assignedUser',
                'terreno.activities',
            ])
            ->find($id);
    }
}
