<?php

namespace App\Services\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\ComiteParecerDepartamento;
use App\Models\Tenant\ComitePendencia;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommitteeService
{
    public const DEFAULT_REQUIRED_DEPARTMENTS = ['comercial', 'engenharia', 'juridico'];

    /**
     * Lista as revisões de comitê com filtros e paginação.
     */
    public function list(array $filters = []): LengthAwarePaginator
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
            $query->whereHas('terreno', function ($builder) use ($search) {
                $builder->where('nome', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Cria uma nova revisão de comitê para um terreno.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): ComiteRevisao
    {
        return DB::transaction(function () use ($data, $user) {
            $terreno = Terreno::query()->with('viabilidadeAtual')->findOrFail($data['terreno_id']);

            if ($terreno->viabilidadeAtual?->approval_status !== 'aprovada') {
                throw new RuntimeException('Somente terrenos com viabilidade aprovada podem seguir para comitê.');
            }

            $review = ComiteRevisao::query()
                ->where('terreno_id', $terreno->id)
                ->whereNull('final_decision')
                ->latest('id')
                ->first();

            if (! $review) {
                $review = ComiteRevisao::create([
                    'terreno_id' => $terreno->id,
                    'viabilidade_id' => $data['viabilidade_id'] ?? $terreno->viabilidadeAtual?->id,
                    'status' => $data['status'] ?? WorkflowStatus::AGUARDANDO_COMITE->value,
                    'required_departments' => $data['required_departments'] ?? self::DEFAULT_REQUIRED_DEPARTMENTS,
                ]);

                EntityActivity::create([
                    'terreno_id' => $terreno->id,
                    'entity_type' => ComiteRevisao::class,
                    'entity_id' => $review->id,
                    'action' => 'committee.created',
                    'user_id' => $user?->id,
                    'summary' => 'Revisão de comitê criada.',
                    'payload_json' => [
                        'required_departments' => $review->required_departments,
                    ],
                    'happened_at' => now(),
                ]);
            }

            app(LandWorkflowService::class)->transition(
                $terreno,
                WorkflowStatus::AGUARDANDO_COMITE->value,
                $user,
                'committee_created',
                'Caso enviado para comitê.',
            );

            return $this->show($review);
        });
    }

    /**
     * Busca os detalhes de uma revisão de comitê.
     */
    public function show(ComiteRevisao $review): ComiteRevisao
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

    /**
     * Registra ou atualiza o parecer de um departamento.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertDepartmentReview(
        ComiteRevisao $review,
        array $data,
        ?User $user = null,
        ?LandWorkflowService $workflowService = null,
    ): ComiteRevisao {
        DB::transaction(function () use ($review, $data, $user) {
            ComiteParecerDepartamento::updateOrCreate(
                [
                    'comite_revisao_id' => $review->id,
                    'department_code' => $data['department_code'],
                ],
                [
                    'reviewer_user_id' => $data['reviewer_user_id'] ?? $user?->id,
                    'decision' => $data['decision'],
                    'comments' => $data['comments'] ?? null,
                    'checklist_completed' => (bool) ($data['checklist_completed'] ?? false),
                    'reviewed_at' => now(),
                ],
            );

            EntityActivity::create([
                'terreno_id' => $review->terreno_id,
                'entity_type' => ComiteRevisao::class,
                'entity_id' => $review->id,
                'action' => 'committee.department_reviewed',
                'user_id' => $user?->id,
                'summary' => "Parecer registrado para {$data['department_code']}.",
                'payload_json' => $data,
                'happened_at' => now(),
            ]);
        });

        if ($review->status === WorkflowStatus::AGUARDANDO_COMITE->value) {
            $review->update(['status' => 'em_comite']);
        }

        return $this->show($review);
    }

    /**
     * Finaliza a revisão do comitê com uma decisão final.
     *
     * @param  array<string, mixed>  $data
     */
    public function finalize(ComiteRevisao $review, array $data, ?User $user, LandWorkflowService $workflowService): ComiteRevisao
    {
        return DB::transaction(function () use ($review, $data, $user, $workflowService) {
            $requiredDepartments = $review->required_departments ?: self::DEFAULT_REQUIRED_DEPARTMENTS;
            $submittedDepartments = $review->pareceresDepartamento()
                ->pluck('department_code')
                ->all();

            foreach ($requiredDepartments as $department) {
                if (! in_array($department, $submittedDepartments, true)) {
                    throw new RuntimeException("Falta parecer obrigatório do departamento {$department}.");
                }
            }

            $decision = $data['final_decision'];

            $review->update([
                'status' => $decision === 'reprovado_comite' ? 'reprovado_comite' : 'aprovado_comite',
                'final_decision' => $decision,
                'final_comments' => $data['final_comments'] ?? null,
                'decided_by' => $user?->id,
                'decided_at' => now(),
            ]);

            if ($decision === 'aprovado_com_ressalvas') {
                $pendencias = $data['pendencias'] ?? [];

                if (empty($pendencias)) {
                    throw new RuntimeException('Aprovação com ressalvas exige ao menos uma pendência.');
                }

                foreach ($pendencias as $pendencia) {
                    ComitePendencia::create([
                        'comite_revisao_id' => $review->id,
                        'terreno_id' => $review->terreno_id,
                        'title' => $pendencia['title'],
                        'description' => $pendencia['description'] ?? null,
                        'severity' => $pendencia['severity'] ?? 'medium',
                        'status' => 'open',
                        'department_code' => $pendencia['department_code'] ?? null,
                        'responsible_user_id' => $pendencia['responsible_user_id'] ?? null,
                        'due_date' => $pendencia['due_date'] ?? null,
                    ]);
                }
            }

            $workflowService->transition(
                $review->terreno()->firstOrFail(),
                $decision === 'reprovado_comite' ? WorkflowStatus::EM_ANALISE->value : WorkflowStatus::NEGOCIACAO_MINUTA->value,
                $user,
                'committee_decision',
                $data['final_comments'] ?? null,
            );

            return $this->show($review->fresh());
        });
    }
}
