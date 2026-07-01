<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Produto;
use App\Models\Tenant\ProdutoHistorico;
use App\Repositories\Contracts\ProdutoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProdutoService
{
    private const AUDITED_FIELDS = [
        'name',
        'description',
        'image',
        'private_area',
        'm2_cost',
        'infra_cost',
        'status',
        'sinal',
        'parcela_obra',
        'parcela_posChave',
        'qtde_parcelas_posChave',
        'demanda_minCef',
        'defasagem_pgtoTerreno',
        'avaliacao_lotesCef',
        'juros_mensalSinal',
        'juros_mensalObra',
        'juros_mensalPosChave',
        'correcao_anualSinal',
        'correcao_anualObra',
        'correcao_anualPosChave',
        'curva_vendas',
        'assist_tecnica1',
        'assist_tecnica2',
        'assist_tecnica3',
        'assist_tecnica4',
        'assist_tecnica5',
        'meses_inicioConstrucao',
        'porcentagem_ConstrucaoStand',
    ];

    public function __construct(
        private readonly ProdutoRepositoryInterface $repository,
    ) {}

    public function list(int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function findById(int $id, bool $withTrashed = false): ?Produto
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function create(array $data): Produto
    {
        return $this->repository->create($data);
    }

    public function update(Produto $produto, array $data): Produto
    {
        return DB::transaction(function () use ($produto, $data): Produto {
            $before = $this->snapshot($produto);
            $updated = $this->repository->update($produto, $data);
            $after = $this->snapshot($updated);

            if ($before !== $after) {
                $this->recordHistory($updated, 'update', $before, $after, $data['updated_by'] ?? null);
            }

            return $updated;
        });
    }

    public function delete(Produto $produto, ?int $userId = null): void
    {
        DB::transaction(function () use ($produto, $userId): void {
            $before = $this->snapshot($produto);
            $this->repository->delete($produto);
            $after = $this->snapshot($produto->refresh());

            $this->recordHistory($produto, 'delete', $before, $after, $userId);
        });
    }

    public function restore(Produto $produto, ?int $userId = null): void
    {
        DB::transaction(function () use ($produto, $userId): void {
            $before = $this->snapshot($produto);
            $this->repository->restore($produto);
            $after = $this->snapshot($produto->refresh());

            $this->recordHistory($produto, 'restore', $before, $after, $userId);
        });
    }

    public function searchForSelect(string $search): Collection
    {
        return $this->repository->searchForSelect($search);
    }

    /**
     * @return array<string, mixed>
     */
    public function history(Produto $produto): array
    {
        $produto->loadMissing(['createdBy', 'updatedBy']);

        return [
            'current' => [
                'id' => $produto->id,
                'name' => $produto->name,
                'status' => $produto->status,
                'created_at' => $produto->created_at?->format('d/m/Y H:i:s'),
                'updated_at' => $produto->updated_at?->format('d/m/Y H:i:s'),
                'created_by_user' => $produto->createdBy ? [
                    'id' => $produto->createdBy->id,
                    'name' => $produto->createdBy->name,
                ] : null,
                'updated_by_user' => $produto->updatedBy ? [
                    'id' => $produto->updatedBy->id,
                    'name' => $produto->updatedBy->name,
                ] : null,
            ],
            'entries' => $produto->historicos()
                ->with('changedBy')
                ->latest()
                ->get()
                ->map(fn (ProdutoHistorico $entry): array => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'before_values' => $entry->before_values,
                    'after_values' => $entry->after_values,
                    'changed_at' => $entry->created_at?->format('d/m/Y H:i:s'),
                    'changed_by_user' => $entry->changedBy ? [
                        'id' => $entry->changedBy->id,
                        'name' => $entry->changedBy->name,
                    ] : null,
                ])
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Produto $produto): array
    {
        $values = $produto->only(self::AUDITED_FIELDS);
        $values['deleted_at'] = $produto->deleted_at?->toJSON();

        return $values;
    }

    private function recordHistory(
        Produto $produto,
        string $action,
        array $before,
        array $after,
        mixed $userId
    ): void {
        ProdutoHistorico::query()->create([
            'produto_id' => $produto->id,
            'action' => $action,
            'before_values' => $before,
            'after_values' => $after,
            'changed_by' => is_numeric($userId) ? (int) $userId : null,
        ]);
    }
}
