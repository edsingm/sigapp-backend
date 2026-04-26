<?php

namespace App\Services\Tenant;

use App\Enums\ProjetoStatus;
use App\Enums\WorkflowStatus;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\StatusHistory;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LandWorkflowService
{
    public const STAGE_CAPTACAO = 'captacao';

    public const STAGE_VIABILIDADE = 'viabilidade';

    public const STAGE_COMITE = 'comite';

    public const STAGE_NEGOCIACAO = 'negociacao_contrato';

    public const STAGE_LEGALIZACAO = 'legalizacao';

    public const STAGE_ENCERRAMENTO = 'encerramento';

    /**
     * Retorna a lista de todos os status de workflow disponíveis.
     *
     * @return array<string, array{stage: string, label: string}>
     */
    public static function statuses(): array
    {
        return collect(WorkflowStatus::cases())->mapWithKeys(fn (WorkflowStatus $s) => [
            $s->value => ['stage' => $s->stage(), 'label' => $s->label()],
        ])->all();
    }

    /**
     * Retorna a matriz de transições permitidas entre status.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitionMatrix(): array
    {
        return [
            WorkflowStatus::EM_ANALISE->value => [WorkflowStatus::AGUARDANDO_VIABILIDADE->value, WorkflowStatus::DESCARTADO->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::AGUARDANDO_VIABILIDADE->value => [WorkflowStatus::VIABILIDADE_APROVADA->value, WorkflowStatus::EM_ANALISE->value, WorkflowStatus::DESCARTADO->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::VIABILIDADE_APROVADA->value => [WorkflowStatus::AGUARDANDO_COMITE->value, WorkflowStatus::EM_ANALISE->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::AGUARDANDO_COMITE->value => [WorkflowStatus::NEGOCIACAO_MINUTA->value, WorkflowStatus::EM_ANALISE->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::NEGOCIACAO_MINUTA->value => [WorkflowStatus::CONTRATO_ASSINADO->value, WorkflowStatus::EM_ANALISE->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::CONTRATO_ASSINADO->value => [WorkflowStatus::LEGALIZANDO->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::LEGALIZANDO->value => [WorkflowStatus::LEGALIZADO_FINALIZADO->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::LEGALIZADO_FINALIZADO->value => [WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::DESCARTADO->value => [WorkflowStatus::EM_ANALISE->value, WorkflowStatus::ARQUIVADO->value],
            WorkflowStatus::ARQUIVADO->value => [],
        ];
    }

    /**
     * Inicializa o workflow de um terreno se ainda não possuir status.
     */
    public function initialize(Terreno $terreno, ?User $user = null): void
    {
        if ($terreno->workflow_status_code) {
            return;
        }

        $this->applyWorkflowState($terreno, WorkflowStatus::EM_ANALISE->value, $user, null, null);
    }

    /**
     * Realiza a transição de status de um terreno.
     *
     * @param  array<string, mixed>  $context
     */
    public function transition(
        Terreno $terreno,
        string $targetStatus,
        ?User $user = null,
        ?string $reasonCode = null,
        ?string $reasonNotes = null,
        array $context = [],
    ): Terreno {
        $targetStatus = $this->normalizeStatus($targetStatus);
        $currentStatus = $this->normalizeStatus($terreno->workflow_status_code ?: WorkflowStatus::EM_ANALISE->value);
        $allowed = self::transitionMatrix()[$currentStatus] ?? [];

        if (! in_array($targetStatus, $allowed, true) && $currentStatus !== $targetStatus) {
            throw new RuntimeException("Transição inválida de {$currentStatus} para {$targetStatus}.");
        }

        $this->assertPrerequisites($terreno, $targetStatus, $context);

        return DB::transaction(function () use ($terreno, $targetStatus, $user, $reasonCode, $reasonNotes, $context, $currentStatus) {
            $freshTerreno = Terreno::query()
                ->with([
                    'proprietarios',
                    'terrenoProdutos',
                    'viabilidadeAtual',
                    'viabilidades',
                    'comiteAtual.pareceresDepartamento',
                    'comiteAtual.pendencias',
                    'negociacaoAtual',
                    'contratoAtual.partes',
                    'legalizacao.etapas',
                    'legalizacao.pendencias',
                ])
                ->findOrFail($terreno->id);

            $this->applyWorkflowState($freshTerreno, $targetStatus, $user, $reasonCode, $reasonNotes, $currentStatus);
            $this->applySideEffects($freshTerreno, $user, $targetStatus, $context);

            return $freshTerreno->fresh([
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
        });
    }

    /**
     * Sincroniza o status do terreno com base no preenchimento dos requisitos mínimos.
     */
    public function syncReadiness(Terreno $terreno, ?User $user = null, ?string $reasonCode = 'terrain_readiness_synced'): Terreno
    {
        $terreno = $terreno->fresh([
            'proprietarios',
            'terrenoProdutos',
            'viabilidadeAtual',
            'comiteAtual',
            'contratoAtual.partes',
            'legalizacao.etapas',
            'legalizacao.pendencias',
        ]);

        if (! $terreno) {
            throw new RuntimeException('Terreno não encontrado para sincronizar workflow.');
        }

        $currentStatus = $this->normalizeStatus($terreno->workflow_status_code ?: WorkflowStatus::EM_ANALISE->value);

        $lockedStatuses = [
            WorkflowStatus::VIABILIDADE_APROVADA->value,
            WorkflowStatus::AGUARDANDO_COMITE->value,
            WorkflowStatus::NEGOCIACAO_MINUTA->value,
            WorkflowStatus::CONTRATO_ASSINADO->value,
            WorkflowStatus::LEGALIZANDO->value,
            WorkflowStatus::LEGALIZADO_FINALIZADO->value,
            WorkflowStatus::DESCARTADO->value,
            WorkflowStatus::ARQUIVADO->value,
        ];

        if (in_array($currentStatus, $lockedStatuses, true)) {
            return $terreno;
        }

        $targetStatus = $this->hasMinimumReadiness($terreno)
            ? WorkflowStatus::AGUARDANDO_VIABILIDADE->value
            : WorkflowStatus::EM_ANALISE->value;

        if ($currentStatus === $targetStatus) {
            return $terreno;
        }

        return $this->transition(
            $terreno,
            $targetStatus,
            $user,
            $reasonCode,
            $targetStatus === WorkflowStatus::AGUARDANDO_VIABILIDADE->value
                ? 'Pré-requisitos mínimos preenchidos para iniciar viabilidade.'
                : 'Checklist mínimo incompleto; terreno voltou para análise.',
        );
    }

    /**
     * Retorna o checklist de prontidão do terreno para cada etapa do workflow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checklist(Terreno $terreno): array
    {
        $committee = $terreno->comiteAtual;
        $contract = $terreno->contratoAtual;
        $legalizacao = $terreno->legalizacao;

        return [
            [
                'code' => 'owners',
                'label' => 'Proprietário cadastrado',
                'completed' => $terreno->proprietarios()->exists(),
            ],
            [
                'code' => 'broker',
                'label' => 'Corretor vinculado',
                'completed' => filled($terreno->corretor_id),
            ],
            [
                'code' => 'products',
                'label' => 'Produto cadastrado',
                'completed' => $terreno->terrenoProdutos()->exists(),
            ],
            [
                'code' => 'viability',
                'label' => 'Viabilidade aprovada',
                'completed' => $terreno->viabilidadeAtual?->approval_status === 'aprovada',
            ],
            [
                'code' => 'committee',
                'label' => 'Comitê aprovado',
                'completed' => in_array($committee?->final_decision, ['aprovado_comite', 'aprovado_com_ressalvas'], true),
            ],
            [
                'code' => 'contract',
                'label' => 'Contrato assinado',
                'completed' => $contract?->signed_at !== null,
            ],
            [
                'code' => 'legalizacao',
                'label' => 'Legalização concluída',
                'completed' => $legalizacao?->status === 'concluido',
            ],
        ];
    }

    /**
     * Retorna as transições disponíveis a partir do status atual.
     *
     * @return array<int, string>
     */
    public function availableTransitions(Terreno $terreno): array
    {
        return self::transitionMatrix()[$this->normalizeStatus($terreno->workflow_status_code ?: WorkflowStatus::EM_ANALISE->value)] ?? [];
    }

    /**
     * Retorna as opções de transição, indicando quais estão bloqueadas por pré-requisitos.
     *
     * @return array{available: array<int, string>, blocked: array<string, string>}
     */
    public function transitionOptions(Terreno $terreno): array
    {
        $available = [];
        $blocked = [];

        foreach ($this->availableTransitions($terreno) as $targetStatus) {
            try {
                $this->assertPrerequisites($terreno, $targetStatus);
                $available[] = $targetStatus;
            } catch (RuntimeException $exception) {
                $blocked[$targetStatus] = $exception->getMessage();
            }
        }

        return [
            'available' => $available,
            'blocked' => $blocked,
        ];
    }

    /**
     * Valida se os pré-requisitos para uma transição foram atendidos.
     *
     * @param  array<string, mixed>  $context
     */
    protected function assertPrerequisites(Terreno $terreno, string $targetStatus, array $context = []): void
    {
        if ($targetStatus === WorkflowStatus::AGUARDANDO_VIABILIDADE->value && ! $this->hasMinimumReadiness($terreno)) {
            throw new RuntimeException('Cadastre ao menos um produto no terreno antes de seguir para viabilidade.');
        }

        if ($targetStatus === WorkflowStatus::VIABILIDADE_APROVADA->value && $terreno->viabilidadeAtual?->approval_status !== 'aprovada') {
            throw new RuntimeException('Não é possível aprovar o terreno sem uma viabilidade aprovada.');
        }

        if ($targetStatus === WorkflowStatus::AGUARDANDO_COMITE->value && $terreno->viabilidadeAtual?->approval_status !== 'aprovada') {
            throw new RuntimeException('Não é possível enviar ao comitê sem viabilidade aprovada.');
        }

        if ($targetStatus === WorkflowStatus::NEGOCIACAO_MINUTA->value) {
            $decision = $terreno->comiteAtual?->final_decision;

            if (! in_array($decision, ['aprovado_comite', 'aprovado_com_ressalvas'], true)) {
                throw new RuntimeException('Não é possível iniciar negociação/minuta sem comitê aprovado.');
            }
        }

        if ($targetStatus === WorkflowStatus::CONTRATO_ASSINADO->value) {
            /** @var Contrato|null $contract */
            $contract = $context['contract'] ?? $terreno->contratoAtual;

            if (! $contract || ! $contract->contract_type || ! $contract->signed_at || ! $contract->file_path || ! $contract->partes()->exists()) {
                throw new RuntimeException('Contrato assinado exige tipo, data, partes vinculadas e documento anexado.');
            }
        }

        if ($targetStatus === WorkflowStatus::LEGALIZANDO->value) {
            $contract = $terreno->contratoAtual;

            if (! $contract || ! $contract->signed_at) {
                throw new RuntimeException('Inicie o projeto somente após contrato assinado.');
            }
        }

        if ($targetStatus === WorkflowStatus::LEGALIZADO_FINALIZADO->value) {
            $legalizacao = $terreno->legalizacao;

            if (! $legalizacao) {
                throw new RuntimeException('Não existe processo de legalização aberto.');
            }

            $hasCriticalIssue = $legalizacao->pendencias()
                ->where('is_critical', true)
                ->where('status', 'open')
                ->exists();

            if ($hasCriticalIssue) {
                throw new RuntimeException('Existem pendências críticas abertas na legalização.');
            }

            $unfinishedRequiredPhases = $legalizacao->etapas()
                ->where('is_required', true)
                ->where('status', '!=', 'concluida')
                ->exists();

            if ($unfinishedRequiredPhases) {
                throw new RuntimeException('Conclua todas as etapas obrigatórias da legalização.');
            }
        }
    }

    /**
     * Aplica o novo estado de workflow ao terreno e registra no histórico.
     */
    protected function applyWorkflowState(
        Terreno $terreno,
        string $targetStatus,
        ?User $user,
        ?string $reasonCode,
        ?string $reasonNotes,
        ?string $previousStatus = null,
    ): void {
        $targetStatus = $this->normalizeStatus($targetStatus);
        $statusMeta = self::statuses()[$targetStatus] ?? null;

        if (! $statusMeta) {
            throw new RuntimeException("Status de workflow desconhecido: {$targetStatus}");
        }

        $previousStage = $terreno->workflow_stage;
        $previousStatus = $this->normalizeStatus($previousStatus ?? $terreno->workflow_status_code);

        $terreno->update([
            'workflow_stage' => $statusMeta['stage'],
            'workflow_status_code' => $targetStatus,
            'workflow_status_changed_at' => now(),
            'workflow_reason_code' => $reasonCode,
            'workflow_reason_notes' => $reasonNotes,
            'updated_by' => $user?->id ?? $terreno->updated_by,
        ]);

        StatusHistory::create([
            'terreno_id' => $terreno->id,
            'old_stage' => $previousStage,
            'old_status_code' => $previousStatus,
            'new_stage' => $statusMeta['stage'],
            'new_status_code' => $targetStatus,
            'changed_by' => $user?->id,
            'reason_code' => $reasonCode,
            'reason' => $reasonNotes,
            'metadata_json' => [
                'label' => $statusMeta['label'],
            ],
            'created_at' => now(),
        ]);

        EntityActivity::create([
            'terreno_id' => $terreno->id,
            'entity_type' => Terreno::class,
            'entity_id' => $terreno->id,
            'action' => 'workflow.transition',
            'user_id' => $user?->id,
            'summary' => "Workflow alterado para {$statusMeta['label']}",
            'payload_json' => [
                'old_stage' => $previousStage,
                'old_status_code' => $previousStatus,
                'new_stage' => $statusMeta['stage'],
                'new_status_code' => $targetStatus,
                'reason_code' => $reasonCode,
                'reason_notes' => $reasonNotes,
            ],
            'happened_at' => now(),
        ]);
    }

    /**
     * Executa efeitos colaterais após uma transição de status bem-sucedida.
     *
     * @param  array<string, mixed>  $context
     */
    protected function applySideEffects(Terreno $terreno, ?User $user, string $targetStatus, array $context = []): void
    {
        if ($targetStatus === WorkflowStatus::NEGOCIACAO_MINUTA->value && ($terreno->comiteAtual?->final_decision === 'aprovado_com_ressalvas')) {
            $pendencias = $terreno->comiteAtual?->pendencias()->count() ?? 0;

            if ($pendencias === 0) {
                Task::create([
                    'terreno_id' => $terreno->id,
                    'related_type' => 'committee',
                    'related_id' => $terreno->comiteAtual?->id,
                    'title' => 'Resolver ressalvas do comitê',
                    'description' => 'A aprovação com ressalvas exige tratativa e acompanhamento.',
                    'assigned_to' => $terreno->responsavel_id,
                    'status' => 'open',
                    'priority' => 'high',
                    'created_by' => $user?->id,
                    'updated_by' => $user?->id,
                ]);
            }
        }

        if ($targetStatus === WorkflowStatus::LEGALIZANDO->value) {
            Projeto::where('terreno_id', $terreno->id)
                ->where('status', ProjetoStatus::EM_VIABILIDADE)
                ->update([
                    'status' => ProjetoStatus::EM_LEGALIZACAO,
                    'updated_by' => $user?->id,
                ]);
        }

        if ($targetStatus === WorkflowStatus::LEGALIZADO_FINALIZADO->value) {
            Projeto::where('terreno_id', $terreno->id)
                ->whereNotIn('status', [ProjetoStatus::CANCELADO, ProjetoStatus::FINALIZADO])
                ->update([
                    'status' => ProjetoStatus::FINALIZADO,
                    'updated_by' => $user?->id,
                ]);
        }

        if (in_array($targetStatus, WorkflowStatus::closure(), true)) {
            Projeto::where('terreno_id', $terreno->id)
                ->whereNotIn('status', [ProjetoStatus::CANCELADO, ProjetoStatus::FINALIZADO])
                ->update([
                    'status' => ProjetoStatus::CANCELADO,
                    'updated_by' => $user?->id,
                ]);
        }
    }

    /**
     * Verifica se o terreno possui os requisitos mínimos preenchidos.
     */
    protected function hasMinimumReadiness(Terreno $terreno): bool
    {
        return $terreno->terrenoProdutos()->exists();
    }

    /**
     * Normaliza o código do status para um valor válido.
     */
    protected function normalizeStatus(?string $status): string
    {
        return WorkflowStatus::tryFrom($status ?? '') !== null
            ? $status
            : WorkflowStatus::EM_ANALISE->value;
    }
}
