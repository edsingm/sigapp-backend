<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\ComiteParecerDepartamento;
use App\Models\Tenant\ComitePendencia;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommitteeRepository
{
    public function findOrFail(int|string $id): ComiteRevisao
    {
        return ComiteRevisao::query()->findOrFail($id);
    }

    /**
     * @param  array{status?: string|null, search?: string|null, per_page?: int|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = ComiteRevisao::query()
            ->with([
                'terreno',
                'terreno.proprietarios',
                'terreno.terrenoProdutos.produto',
                'terreno.corretorExterno',
                'terreno.viabilidadeAtual',
                'viabilidade',
                'pareceresDepartamento',
                'pendencias',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('terreno', function ($builder) use ($search): void {
                $builder->where('nome', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate((int) ($filters['per_page'] ?? 10));
    }

    public function findTerrenoForCommitteeOrFail(int|string $terrenoId): Terreno
    {
        return Terreno::query()->with('viabilidadeAtual')->findOrFail($terrenoId);
    }

    public function findOpenReviewByTerreno(int $terrenoId): ?ComiteRevisao
    {
        return ComiteRevisao::query()
            ->where('terreno_id', $terrenoId)
            ->whereNull('final_decision')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ComiteRevisao
    {
        return ComiteRevisao::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ComiteRevisao $review, array $data): ComiteRevisao
    {
        $review->update($data);

        return $review->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertDepartmentReview(ComiteRevisao $review, array $data): ComiteParecerDepartamento
    {
        return ComiteParecerDepartamento::query()->updateOrCreate(
            [
                'comite_revisao_id' => $review->id,
                'department_code' => $data['department_code'],
            ],
            [
                'reviewer_user_id' => $data['reviewer_user_id'] ?? null,
                'decision' => $data['decision'],
                'comments' => $data['comments'] ?? null,
                'checklist_completed' => (bool) ($data['checklist_completed'] ?? false),
                'reviewed_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPendencia(ComiteRevisao $review, array $data): ComitePendencia
    {
        return ComitePendencia::query()->create([
            'comite_revisao_id' => $review->id,
            'terreno_id' => $review->terreno_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'severity' => $data['severity'] ?? 'medium',
            'status' => 'open',
            'department_code' => $data['department_code'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function reviewedDepartmentCodes(ComiteRevisao $review): array
    {
        /** @var list<string> $codes */
        $codes = $review->pareceresDepartamento()->pluck('department_code')->all();

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createActivity(array $payload): EntityActivity
    {
        return EntityActivity::query()->create($payload);
    }

    public function loadDetail(ComiteRevisao $review): ComiteRevisao
    {
        return $review->load([
            'terreno',
            'terreno.cidade',
            'terreno.responsavel',
            'terreno.corretorExterno',
            'terreno.proprietarios',
            'terreno.contatos',
            'terreno.terrenoProdutos.produto',
            'terreno.documentos',
            'terreno.informacoes.user',
            'terreno.viabilidades.createdBy',
            'terreno.viabilidadeAtual.createdBy',
            'terreno.viabilidadeAtual.approvalDecidedBy',
            'terreno.viabilidadeAtual.secoes',
            'terreno.viabilidadeAtual.aprovacoes.user',
            'viabilidade',
            'pareceresDepartamento',
            'pendencias',
        ]);
    }
}
