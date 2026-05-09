<?php

namespace App\Services\Tenant\Viabilidade\v1;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Repositories\Tenant\ViabilidadeRepository;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Tenant\LandWorkflowService;
use App\Services\Tenant\MobilePushService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViabilidadeService
{
    public function __construct(
        private readonly ViabilidadeUnificadoService $unificadoService,
        private readonly LandWorkflowService $workflowService,
        private readonly MobilePushService $mobilePushService,
        private readonly PermissionNameResolver $permissions,
        private readonly ViabilidadeRepository $repository,
    ) {}

    public function findOrFail(int|string $id): Viabilidade
    {
        return $this->repository->findOrFail($id);
    }

    public function findWithTrashedOrFail(int|string $id): Viabilidade
    {
        return $this->repository->findWithTrashedOrFail($id);
    }

    /**
     * Listar viabilidades por terreno
     */
    public function listarViabilidadesPorTerreno(int $terrenoId): EloquentCollection
    {
        return $this->repository->listByTerreno($terrenoId);
    }

    /**
     * Buscar viabilidade atual (mais recente) de um terreno
     */
    public function buscarViabilidadeAtual(int $terrenoId): ?Viabilidade
    {
        return $this->repository->latestByTerreno($terrenoId);
    }

    /**
     * Comparar duas viabilidades
     */
    public function compararViabilidades(int $id1, int $id2): array
    {
        $v1 = $this->buscarViabilidadeComDre($id1);
        $v2 = $this->buscarViabilidadeComDre($id2);

        return [
            'viabilidade_1' => $v1,
            'viabilidade_2' => $v2,
        ];
    }

    /**
     * Criar nova viabilidade e gerar DRE automaticamente
     */
    public function criarViabilidadeComDre(array $dados, ?User $actor = null): array
    {
        return DB::transaction(function () use ($dados, $actor) {
            $actor ??= Auth::user();
            $this->validarDados($dados);
            $terreno = $this->repository->findTerrenoOrFail($dados['terreno_id']);
            $nextVersion = $this->repository->nextVersionForTerreno((int) $dados['terreno_id']);

            $this->repository->clearCurrentForTerreno((int) $dados['terreno_id']);

            $viabilidade = $this->repository->create([
                ...collect($dados)->except(['produtos'])->toArray(),
                'version' => $nextVersion,
                'is_current' => true,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $dados['terreno_id'],
                $viabilidade->id,
                $dados['produtos'] ?? null
            );

            $viabilidade = $this->repository->update($viabilidade, [
                'resultados_dre' => $dreResultados,
            ]);

            $this->advanceWorkflowForNewViability(
                $terreno,
                $viabilidade->version,
            );

            return [
                'viabilidade' => $this->repository->loadDefaultRelations($viabilidade),
                'dre_resultados' => $dreResultados,
            ];
        });
    }

    /**
     * Atualizar viabilidade e recalcular DRE
     */
    public function atualizarViabilidadeComDre(Viabilidade|int|string $viabilidade, array $dados, ?User $actor = null): array
    {
        return DB::transaction(function () use ($viabilidade, $dados, $actor) {
            $actor ??= Auth::user();
            $viabilidade = $viabilidade instanceof Viabilidade ? $viabilidade : $this->repository->findOrFail($viabilidade);

            // Se houver validação específica de update, chamar aqui
            // $this->validarDados($dados); // Opcional, dependendo da regra

            $viabilidade = $this->repository->update($viabilidade, [
                ...collect($dados)->except(['produtos'])->toArray(),
                'updated_by' => $actor?->id,
            ]);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $viabilidade->terreno_id,
                $viabilidade->id,
                $dados['produtos'] ?? null
            );

            $viabilidade = $this->repository->update($viabilidade, [
                'resultados_dre' => $dreResultados,
            ]);

            return [
                'viabilidade' => $this->repository->loadDefaultRelations($viabilidade),
                'dre_resultados' => $dreResultados,
            ];
        });
    }

    /**
     * Buscar viabilidade com DRE por ID
     */
    public function buscarViabilidadeComDre(Viabilidade|int|string $viabilidade): array
    {
        $viabilidade = $viabilidade instanceof Viabilidade ? $viabilidade : $this->repository->findOrFail($viabilidade);
        $viabilidade = $this->repository->loadDreRelations($viabilidade);

        $dreResultados = $viabilidade->resultados_dre;

        if ($this->precisaRecalcularDre($dreResultados)) {
            $dreResultados = $this->recalcularDre($viabilidade)['dre_resultados'];
        }

        return [
            'viabilidade' => $this->repository->loadDreRelations($viabilidade),
            'dre_resultados' => $dreResultados,
        ];
    }

    private function precisaRecalcularDre(mixed $dreResultados): bool
    {
        if (empty($dreResultados)) {
            return true;
        }

        if (! isset($dreResultados['indicadores']) || ! isset($dreResultados['totais'])) {
            return true;
        }

        $fluxo = $dreResultados['fluxo_mensal'] ?? [];
        $primeiroMes = ! empty($fluxo) ? reset($fluxo) : null;

        if (! $primeiroMes) {
            return true;
        }

        if (! isset($primeiroMes['receitas']['recursos_proprios'])) {
            return true;
        }

        // Compatibilidade: versões antigas persistiram POC zerado por divergência
        // de chaves em despesas. Quando houver receita no DRE e POC zerado, força recálculo.
        $receitaTotalVendas = (float) ($dreResultados['dre_itens']['receita_total_vendas'] ?? 0.0);
        $receitaCaixaTotal = (float) ($dreResultados['dre_caixa']['receita_total'] ?? 0.0);
        $pocReceita = (float) ($dreResultados['dre_contabil_poc']['receita_reconhecida_poc'] ?? 0.0);
        $pocBlocosReceita = (float) ($dreResultados['dre_contabil_poc_mensal_blocos']['resumo']['receita_reconhecida_poc_total'] ?? 0.0);

        if (($receitaTotalVendas > 0 || $receitaCaixaTotal > 0) && $pocReceita === 0.0 && $pocBlocosReceita === 0.0) {
            return true;
        }

        return false;
    }

    /**
     * Listar todas as viabilidades com paginação e filtros
     */
    public function listarTodasViabilidades(array $filtros = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filtros);
    }

    /**
     * Validar dados de viabilidade
     */
    public function validarDados(array $dados): array
    {
        if (empty($dados['terreno_id'])) {
            throw new Exception('ID do terreno é obrigatório');
        }

        if (! $this->repository->terrenoExists($dados['terreno_id'])) {
            throw new Exception('Terreno não encontrado');
        }

        // Validação de numéricos pode ser simplificada com filter_var ou validator do Laravel
        // Mantendo lógica original mas simplificada e centralizada
        $camposNumericos = Viabilidade::CAMPOS_FINANCEIROS;

        foreach ($camposNumericos as $campo) {
            if (isset($dados[$campo]) && ! is_numeric($dados[$campo])) {
                throw new Exception("Campo {$campo} deve ser numérico");
            }
        }

        if (isset($dados['prazo_obra'])) {
            $prazosValidos = ['18', '24', '36', '48', '60'];
            if (! in_array((string) $dados['prazo_obra'], $prazosValidos)) {
                throw new Exception('Prazo de obra deve ser: 18, 24, 36, 48 ou 60 meses');
            }
        }

        return $dados;
    }

    /**
     * Duplicar viabilidade (para criar nova versão)
     */
    public function duplicarViabilidade(int $viabilidadeId, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidadeOriginal = $this->repository->findOrFail($viabilidadeId);
        $nextVersion = $this->repository->nextVersionForTerreno($viabilidadeOriginal->terreno_id);

        $dadosNova = $viabilidadeOriginal->toArray();
        $dadosNova['created_by'] = $actor?->id;
        $dadosNova['updated_by'] = $actor?->id;
        $dadosNova['resultados_dre'] = null;
        $dadosNova['approval_status'] = 'pendente';
        $dadosNova['approval_requested_at'] = null;
        $dadosNova['approval_decided_at'] = null;
        $dadosNova['approval_decided_by'] = null;
        $dadosNova['approval_notes'] = null;
        $dadosNova['submitted_at'] = null;
        $dadosNova['locked_at'] = null;
        $dadosNova['status'] = 'rascunho';
        $dadosNova['version'] = $nextVersion;
        $dadosNova['is_current'] = true;

        $this->repository->clearCurrentForTerreno($viabilidadeOriginal->terreno_id);

        // Remove campos gerados automaticamente
        unset($dadosNova['id'], $dadosNova['created_at'], $dadosNova['updated_at'], $dadosNova['deleted_at']);

        $novaViabilidade = $this->repository->create($dadosNova);
        $this->repository->copySections($viabilidadeOriginal, $novaViabilidade);

        return $this->repository->loadDefaultRelations($novaViabilidade);
    }

    /**
     * Excluir viabilidade (soft delete)
     */
    public function excluirViabilidade(int $viabilidadeId): bool
    {
        $viabilidade = $this->repository->findOrFail($viabilidadeId);

        return $this->repository->delete($viabilidade);
    }

    /**
     * Recalcular DRE de uma viabilidade existente
     */
    public function recalcularDre(Viabilidade|int|string $viabilidade, ?User $actor = null): array
    {
        return DB::transaction(function () use ($viabilidade, $actor) {
            $actor ??= Auth::user();
            $viabilidade = $viabilidade instanceof Viabilidade ? $viabilidade : $this->repository->findOrFail($viabilidade);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $viabilidade->terreno_id,
                $viabilidade->id
            );

            $viabilidade = $this->repository->update($viabilidade, [
                'resultados_dre' => $dreResultados,
                'updated_by' => $actor?->id,
                'updated_at' => now(),
            ]);

            return [
                'viabilidade' => $this->repository->loadDefaultRelations($viabilidade),
                'dre_resultados' => $dreResultados,
            ];
        });
    }

    public function registrarAprovacao(Viabilidade $viabilidade, string $decision, ?string $comments = null, ?User $actor = null): void
    {
        $actor ??= Auth::user();

        $this->repository->createApproval($viabilidade, $actor?->id, $decision, $comments);
    }

    public function ativar(int|string $viabilidadeId, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidade = $this->repository->findOrFail($viabilidadeId);

        return $this->repository->loadDefaultRelations(
            $this->repository->update($viabilidade, [
                'status' => 'ativo',
                'updated_by' => $actor?->id,
            ])
        );
    }

    public function solicitarAprovacao(int|string $viabilidadeId, ?string $approvalNotes, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidade = $this->repository->loadDefaultRelations(
            $this->repository->findOrFail($viabilidadeId)
        );

        $viabilidade = $this->repository->update($viabilidade, [
            'approval_status' => 'em_aprovacao',
            'approval_requested_at' => now(),
            'submitted_at' => now(),
            'approval_decided_at' => null,
            'approval_decided_by' => null,
            'approval_notes' => $approvalNotes,
            'updated_by' => $actor?->id,
        ]);

        $terreno = $viabilidade->terreno ?? $this->repository->findTerrenoOrFail($viabilidade->terreno_id);

        $this->workflowService->transition(
            $terreno,
            WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            $actor,
            'viability_submitted',
            $approvalNotes,
        );

        $this->mobilePushService->notifyUsersWithPermission(
            (string) $this->permissions->forModel(Viabilidade::class, 'approve'),
            [
                'title' => 'Viabilidade aguardando aprovação',
                'body' => "A viabilidade do terreno {$terreno->nome} aguarda decisão.",
                'type' => 'viabilidade.solicitar_aprovacao',
                'entity_type' => 'viabilidade',
                'entity_id' => (string) $viabilidade->id,
                'target_route' => "/terrenos/{$viabilidade->terreno_id}",
                'payload' => [
                    'tenant_slug' => tenant('slug'),
                    'viabilidade_id' => $viabilidade->id,
                    'terreno_id' => $viabilidade->terreno_id,
                ],
            ],
            $actor
        );

        return $this->repository->loadDefaultRelations($viabilidade);
    }

    public function decidirAprovacao(int|string $viabilidadeId, string $decision, ?string $approvalNotes, ?User $actor = null): Viabilidade
    {
        $actor ??= Auth::user();
        $viabilidade = $this->repository->loadDefaultRelations(
            $this->repository->findOrFail($viabilidadeId)
        );

        $approvalStatus = $viabilidade->approval_status ?? ($viabilidade->status === 'ativo' ? 'aprovada' : 'pendente');

        if ($approvalStatus !== 'em_aprovacao') {
            throw ValidationException::withMessages([
                'approval_notes' => ['A viabilidade precisa estar em aprovação antes desta decisão.'],
            ]);
        }

        $payload = [
            'approval_status' => $decision,
            'approval_decided_at' => now(),
            'approval_decided_by' => $actor?->id,
            'approval_notes' => $approvalNotes ?? $viabilidade->approval_notes,
            'updated_by' => $actor?->id,
        ];

        if ($decision === 'aprovada') {
            $payload['status'] = 'ativo';
            $payload['locked_at'] = now();
        } else {
            $payload['status'] = 'rascunho';
            $payload['locked_at'] = null;
        }

        $viabilidade = $this->repository->update($viabilidade, $payload);
        $this->registrarAprovacao($viabilidade, $decision, $approvalNotes, $actor);

        $terreno = $viabilidade->terreno ?? $this->repository->findTerrenoOrFail($viabilidade->terreno_id);

        $this->workflowService->transition(
            $terreno,
            $decision === 'aprovada' ? WorkflowStatus::VIABILIDADE_APROVADA->value : WorkflowStatus::EM_ANALISE->value,
            $actor,
            'viability_decided',
            $approvalNotes,
        );

        $this->mobilePushService->notifyAllUsers(
            [
                'title' => $decision === 'aprovada'
                    ? 'Viabilidade aprovada'
                    : 'Viabilidade reprovada',
                'body' => $decision === 'aprovada'
                    ? "A viabilidade do terreno {$terreno->nome} foi aprovada."
                    : "A viabilidade do terreno {$terreno->nome} foi reprovada.",
                'type' => $decision === 'aprovada'
                    ? 'viabilidade.aprovada'
                    : 'viabilidade.reprovada',
                'entity_type' => 'viabilidade',
                'entity_id' => (string) $viabilidade->id,
                'target_route' => "/terrenos/{$viabilidade->terreno_id}",
                'payload' => [
                    'tenant_slug' => tenant('slug'),
                    'viabilidade_id' => $viabilidade->id,
                    'terreno_id' => $viabilidade->terreno_id,
                ],
            ],
            $actor
        );

        return $this->repository->loadDefaultRelations($viabilidade);
    }

    public function restore(int|string $viabilidadeId): Viabilidade
    {
        $viabilidade = $this->repository->findWithTrashedOrFail($viabilidadeId);

        return $this->repository->loadDefaultRelations(
            $this->repository->restore($viabilidade)
        );
    }

    /**
     * @return Collection<int, array{id: int, label: string, terreno_id: int}>
     */
    public function forSelect(?int $terrenoId = null): Collection
    {
        return $this->repository->forSelect($terrenoId)->map(function (Viabilidade $viabilidade): array {
            $data = $viabilidade->created_at?->format('d/m/Y H:i') ?? '';

            return [
                'id' => $viabilidade->id,
                'label' => "Viabilidade #{$viabilidade->id} - {$viabilidade->terreno?->nome} ({$data})",
                'terreno_id' => $viabilidade->terreno_id,
            ];
        })->values();
    }

    /**
     * @return array{viabilidade_1: array<string, mixed>, viabilidade_2: array<string, mixed>}
     */
    public function compareByIds(int $id1, int $id2): array
    {
        return [
            'viabilidade_1' => $this->buscarViabilidadeComDre($id1),
            'viabilidade_2' => $this->buscarViabilidadeComDre($id2),
        ];
    }

    /**
     * @return array{viabilidade: Viabilidade, dre: array<string, mixed>, dataGeracao: string}
     */
    public function exportData(int|string $viabilidadeId): array
    {
        $resultado = $this->buscarViabilidadeComDre($viabilidadeId);
        /** @var Viabilidade $viabilidade */
        $viabilidade = $resultado['viabilidade'];
        $dre = $resultado['dre_resultados'];

        if (! $dre || ! isset($dre['totais'])) {
            $resultado = $this->recalcularDre($viabilidade);
            $dre = $resultado['dre_resultados'];
        }

        if (! $dre) {
            throw new Exception('Não foi possível carregar ou gerar os dados do DRE para esta viabilidade.');
        }

        return [
            'viabilidade' => $viabilidade,
            'dre' => $dre,
            'dataGeracao' => now()->format('d/m/Y H:i'),
        ];
    }

    protected function advanceWorkflowForNewViability(Terreno $terreno, int $version): void
    {
        $user = Auth::user();
        $reasonNotes = "Viabilidade versão {$version} criada.";

        $currentStatus = $terreno->workflow_status_code ?? null;

        if ($currentStatus === WorkflowStatus::AGUARDANDO_VIABILIDADE->value) {
            return;
        }

        $statusesAposViabilidade = [
            WorkflowStatus::VIABILIDADE_APROVADA->value,
            WorkflowStatus::AGUARDANDO_COMITE->value,
            WorkflowStatus::NEGOCIACAO_MINUTA->value,
            WorkflowStatus::CONTRATO_ASSINADO->value,
            WorkflowStatus::LEGALIZANDO->value,
            WorkflowStatus::LEGALIZADO_FINALIZADO->value,
        ];

        if (in_array($currentStatus, $statusesAposViabilidade, true)) {
            return;
        }

        $this->workflowService->transition(
            $terreno,
            WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            $user,
            'viability_created',
            $reasonNotes,
        );
    }
}
