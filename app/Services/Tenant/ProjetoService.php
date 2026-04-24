<?php

namespace App\Services\Tenant;

use App\Enums\ProjetoStatus;
use App\Enums\WorkflowStatus;
use App\Http\Resources\Tenant\ComiteRevisaoResource;
use App\Http\Resources\Tenant\ContratoResource;
use App\Http\Resources\Tenant\EntityActivityResource;
use App\Http\Resources\Tenant\LegalizacaoResource;
use App\Http\Resources\Tenant\NegociacaoResource;
use App\Http\Resources\Tenant\ProjetoResource;
use App\Http\Resources\Tenant\TaskResource;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Http\Resources\Tenant\ViabilidadeResource;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjetoService
{
    public function __construct(
        protected LegalizacaoService $legalizacaoService,
        protected LandWorkflowService $workflowService,
        protected \App\Repositories\Contracts\ProjetoRepositoryInterface $repository,
    ) {}

    /**
     * Lista os projetos com filtros e paginação.
     */
    public function listar(array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->repository->listWithFilters($filters);

        $paginator->getCollection()->each(function (Projeto $projeto) {
            $this->refreshStatus($projeto);
        });

        return $paginator;
    }

    /**
     * Lista terrenos que podem iniciar um novo projeto (contrato assinado e sem projeto ativo).
     */
    public function listarTerrenosElegiveis(array $filters = []): LengthAwarePaginator
    {
        $query = Terreno::query()
            ->select('terrenos.*')
            ->with(['cidade', 'responsavel', 'proprietarios', 'informacoes'])
            ->where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)
            ->whereDoesntHave('projetoAtivo');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhere('endereco', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Cria um novo projeto vinculado a um terreno.
     */
    public function criar(array $data): Projeto
    {
        return DB::transaction(function () use ($data) {
            $terreno = $this->validarTerrenoElegivel($data['terreno_id']);
            $this->validarProjetoAtivoUnico($terreno->id);

            $projeto = $this->repository->create([
                'nome' => $data['nome'],
                'terreno_id' => $terreno->id,
                'responsavel_id' => $data['responsavel_id'] ?? null,
                'status' => ProjetoStatus::EM_LEGALIZACAO,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            if (! $terreno->legalizacao) {
                $this->legalizacaoService->criar([
                    'terreno_id' => $terreno->id,
                    'nome' => "Legalização - {$terreno->nome}",
                    'responsavel_id' => $data['responsavel_id'] ?? null,
                ]);
            } else {
                $this->workflowService->transition(
                    $terreno,
                    WorkflowStatus::LEGALIZANDO->value,
                    Auth::user(),
                    'project_started',
                    'Projeto iniciado a partir do terreno com contrato assinado.',
                );
            }

            return $this->buscar($projeto->id);
        });
    }

    /**
     * Busca os detalhes de um projeto por ID, incluindo todos os relacionamentos.
     */
    public function buscar(int $id): Projeto
    {
        $projeto = $this->repository->findWithFullRelations($id);

        if (! $projeto) {
            throw new \RuntimeException('Projeto não encontrado.');
        }

        $this->refreshStatus($projeto);

        return $projeto->fresh([
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
        ]);
    }

    /**
     * Atualiza os dados de um projeto.
     */
    public function atualizar(Projeto $projeto, array $data): Projeto
    {
        $payload = [
            'updated_by' => Auth::id(),
        ];

        if (array_key_exists('nome', $data)) {
            $payload['nome'] = $data['nome'];
        }

        if (array_key_exists('responsavel_id', $data)) {
            $payload['responsavel_id'] = $data['responsavel_id'];
        }

        if (array_key_exists('status', $data) && ! in_array($projeto->status, [ProjetoStatus::FINALIZADO, ProjetoStatus::CANCELADO], true)) {
            $payload['status'] = $data['status'];
        }

        $projeto->update($payload);

        $this->refreshStatus($projeto);

        return $this->buscar($projeto->id);
    }

    /**
     * Cancela um projeto ativo.
     */
    public function cancelar(Projeto $projeto): Projeto
    {
        $projeto->update([
            'status' => ProjetoStatus::CANCELADO,
            'updated_by' => Auth::id(),
        ]);

        return $this->buscar($projeto->id);
    }

    /**
     * Finaliza o projeto após a conclusão da legalização.
     */
    public function marcarProntoParaRegistro(Projeto $projeto): Projeto
    {
        $projeto = $this->buscar($projeto->id);
        $legalizacao = $projeto->terreno?->legalizacao;

        if (! $legalizacao || $legalizacao->status !== 'concluido') {
            throw new \RuntimeException('A legalização precisa estar concluída antes de finalizar o projeto.');
        }

        return DB::transaction(function () use ($projeto) {
            $projeto->update([
                'status' => ProjetoStatus::FINALIZADO,
                'updated_by' => Auth::id(),
            ]);

            $this->workflowService->transition(
                $projeto->terreno,
                WorkflowStatus::LEGALIZADO_FINALIZADO->value,
                Auth::user(),
                'project_finished',
                'Projeto finalizado após cumprimento das etapas da legalização.',
            );

            return $this->buscar($projeto->id);
        });
    }

    /**
     * Retorna as ações permitidas no workflow do projeto para o usuário atual.
     */
    public function workflow(Projeto $projeto, ?User $user): array
    {
        $terreno = $projeto->terreno;
        $legalizacao = $terreno?->legalizacao;
        $projectIsActive = ! in_array($projeto->status, [
            ProjetoStatus::FINALIZADO,
            ProjetoStatus::CANCELADO,
            ProjetoStatus::PRONTO_PARA_REGISTRO,
        ], true);

        return [
            'can_request_viability_approval' => false,
            'can_approve_viability' => false,
            'can_start_legalizacao' => $projectIsActive && ! $legalizacao && $terreno?->workflow_status_code === WorkflowStatus::CONTRATO_ASSINADO->value,
            'can_mark_ready_for_registry' => $projectIsActive && $legalizacao?->status === 'concluido',
            'next_step' => $this->nextStep($projeto, $legalizacao),
        ];
    }

    /**
     * Prepara o payload completo para a área de trabalho do projeto.
     */
    public function workspacePayload(Projeto $projeto, ?User $user): array
    {
        $terreno = $projeto->terreno;

        return [
            'projeto' => new ProjetoResource($projeto),
            'terreno' => $terreno ? new TerrenoResource($terreno) : null,
            'viabilidade_atual' => $terreno?->viabilidadeAtual ? new ViabilidadeResource($terreno->viabilidadeAtual) : null,
            'comite_atual' => $terreno?->comiteAtual ? new ComiteRevisaoResource($terreno->comiteAtual) : null,
            'negociacao_atual' => $terreno?->negociacaoAtual ? new NegociacaoResource($terreno->negociacaoAtual) : null,
            'contrato_atual' => $terreno?->contratoAtual ? new ContratoResource($terreno->contratoAtual) : null,
            'legalizacao' => $terreno?->legalizacao ? new LegalizacaoResource($terreno->legalizacao) : null,
            'tasks' => $terreno ? TaskResource::collection($terreno->tasks)->resolve() : [],
            'history' => $terreno ? EntityActivityResource::collection($terreno->activities)->resolve() : [],
            'workflow' => $this->workflow($projeto, $user),
        ];
    }

    /**
     * Atualiza o status do projeto com base nas relações (legalização, workflow do terreno).
     */
    public function refreshStatus(Projeto $projeto): Projeto
    {
        if (in_array($projeto->status, [
            ProjetoStatus::FINALIZADO,
            ProjetoStatus::CANCELADO,
            ProjetoStatus::PRONTO_PARA_REGISTRO,
        ], true)) {
            return $projeto;
        }

        $projeto->loadMissing([
            'terreno.legalizacao',
        ]);

        $resolvedStatus = $this->resolveStatusFromRelations($projeto->terreno);

        if ($projeto->status !== $resolvedStatus) {
            $projeto->forceFill([
                'status' => $resolvedStatus,
                'updated_by' => Auth::id() ?? $projeto->updated_by,
            ])->save();
        }

        return $projeto;
    }

    /**
     * Valida se o terreno pode iniciar um projeto.
     */
    protected function validarTerrenoElegivel(int $terrenoId): Terreno
    {
        $terreno = $this->repository->findTerrenoElegivel($terrenoId);

        if ($terreno->workflow_status_code !== WorkflowStatus::CONTRATO_ASSINADO->value) {
            throw new \RuntimeException('Somente terrenos com status "Contrato Assinado" podem iniciar um projeto.');
        }

        $this->validarProjetoAtivoUnico($terreno->id);

        return $terreno;
    }

    /**
     * Garante que não existam múltiplos projetos ativos para o mesmo terreno.
     */
    protected function validarProjetoAtivoUnico(int $terrenoId): void
    {
        if ($this->repository->existsActiveProjetoForTerreno($terrenoId)) {
            throw new \RuntimeException('Já existe um projeto ativo para este terreno.');
        }
    }

    /**
     * Resolve o status lógico do projeto com base no estado do terreno e legalização.
     */
    protected function resolveStatusFromRelations(?Terreno $terreno): string
    {
        if (in_array($terreno?->workflow_status_code, [WorkflowStatus::LEGALIZADO_FINALIZADO->value], true)) {
            return ProjetoStatus::FINALIZADO;
        }

        if ($terreno?->legalizacao || $terreno?->workflow_status_code === WorkflowStatus::LEGALIZANDO->value) {
            return ProjetoStatus::EM_LEGALIZACAO;
        }

        return ProjetoStatus::EM_VIABILIDADE;
    }

    /**
     * Determina a próxima etapa sugerida para o projeto.
     */
    protected function nextStep(Projeto $projeto, mixed $legalizacao): string
    {
        if (in_array($projeto->status, [ProjetoStatus::FINALIZADO, ProjetoStatus::PRONTO_PARA_REGISTRO], true)) {
            return 'Projeto concluído e terreno finalizado.';
        }

        if ($projeto->status === ProjetoStatus::CANCELADO) {
            return 'Projeto cancelado.';
        }

        if (! $legalizacao) {
            return 'Iniciar a legalização do terreno.';
        }

        if ($legalizacao->status !== 'concluido') {
            return 'Concluir as etapas obrigatórias da legalização.';
        }

        return 'Finalizar o projeto e atualizar o terreno para legalizado/finalizado.';
    }
}
