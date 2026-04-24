<?php

namespace App\Services\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\User;
use App\Repositories\Tenant\CommitteeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitteeService
{
    public const DEFAULT_REQUIRED_DEPARTMENTS = ['comercial', 'engenharia', 'juridico'];

    public function __construct(
        private readonly CommitteeRepository $repository,
        private readonly LandWorkflowService $workflowService,
    ) {}

    /**
     * Lista as revisões de comitê com filtros e paginação.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function findOrFail(int|string $id): ComiteRevisao
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Cria uma nova revisão de comitê para um terreno.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): ComiteRevisao
    {
        return DB::transaction(function () use ($data, $user) {
            $terreno = $this->repository->findTerrenoForCommitteeOrFail($data['terreno_id']);

            if ($terreno->viabilidadeAtual?->approval_status !== 'aprovada') {
                throw ValidationException::withMessages([
                    'terreno_id' => ['Somente terrenos com viabilidade aprovada podem seguir para comitê.'],
                ]);
            }

            $review = $this->repository->findOpenReviewByTerreno($terreno->id);

            if (! $review) {
                $review = $this->repository->create([
                    'terreno_id' => $terreno->id,
                    'viabilidade_id' => $data['viabilidade_id'] ?? $terreno->viabilidadeAtual?->id,
                    'status' => $data['status'] ?? WorkflowStatus::AGUARDANDO_COMITE->value,
                    'required_departments' => $data['required_departments'] ?? self::DEFAULT_REQUIRED_DEPARTMENTS,
                ]);

                $this->repository->createActivity([
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

            $this->workflowService->transition(
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
        return $this->repository->loadDetail($review);
    }

    public function showById(int|string $reviewId): ComiteRevisao
    {
        return $this->show($this->repository->findOrFail($reviewId));
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
    ): ComiteRevisao {
        if (! $review->exists) {
            throw new ModelNotFoundException('Comitê não encontrado.');
        }

        DB::transaction(function () use ($review, $data, $user) {
            $this->repository->upsertDepartmentReview($review, [
                ...$data,
                'reviewer_user_id' => $data['reviewer_user_id'] ?? $user?->id,
            ]);

            $this->repository->createActivity([
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
            $this->repository->update($review, ['status' => 'em_comite']);
        }

        return $this->show($review);
    }

    /**
     * Finaliza a revisão do comitê com uma decisão final.
     *
     * @param  array<string, mixed>  $data
     */
    public function finalize(ComiteRevisao $review, array $data, ?User $user): ComiteRevisao
    {
        if (! $review->exists) {
            throw new ModelNotFoundException('Comitê não encontrado.');
        }

        return DB::transaction(function () use ($review, $data, $user) {
            $requiredDepartments = $review->required_departments ?: self::DEFAULT_REQUIRED_DEPARTMENTS;
            $submittedDepartments = $this->repository->reviewedDepartmentCodes($review);

            foreach ($requiredDepartments as $department) {
                if (! in_array($department, $submittedDepartments, true)) {
                    throw ValidationException::withMessages([
                        'final_decision' => ["Falta parecer obrigatório do departamento {$department}."],
                    ]);
                }
            }

            $decision = $data['final_decision'];

            $review = $this->repository->update($review, [
                'status' => $decision === 'reprovado_comite' ? 'reprovado_comite' : 'aprovado_comite',
                'final_decision' => $decision,
                'final_comments' => $data['final_comments'] ?? null,
                'decided_by' => $user?->id,
                'decided_at' => now(),
            ]);

            if ($decision === 'aprovado_com_ressalvas') {
                $pendencias = $data['pendencias'] ?? [];

                if (empty($pendencias)) {
                    throw ValidationException::withMessages([
                        'pendencias' => ['Aprovação com ressalvas exige ao menos uma pendência.'],
                    ]);
                }

                foreach ($pendencias as $pendencia) {
                    $this->repository->createPendencia($review, $pendencia);
                }
            }

            $this->workflowService->transition(
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
