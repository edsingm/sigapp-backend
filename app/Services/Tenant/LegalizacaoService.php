<?php

namespace App\Services\Tenant;

use App\Enums\LegalizacaoEtapaStatus;
use App\Enums\LegalizacaoStatus;
use App\Enums\WorkflowStatus;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoDependencia;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Tenant\LegalizacaoEtapaRepository;
use App\Repositories\Tenant\LegalizacaoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalizacaoService
{
    public function __construct(
        protected LegalizacaoRepository $repository,
        protected LegalizacaoEtapaRepository $etapaRepository,
        protected LandWorkflowService $workflowService,
    ) {}

    /**
     * Listar legalizações com filtros e paginação
     */
    public function listar(array $filtros = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filtros);
    }

    public function findOrFail(int|string $id): Legalizacao
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Buscar legalização por ID com relacionamentos
     */
    public function buscar(int $legalizacaoId): array
    {
        $legalizacao = $this->repository->loadDetail($this->repository->findOrFail($legalizacaoId));

        return [
            'legalizacao' => $legalizacao,
            'etapas' => $legalizacao->etapas->sortBy('ordem')->values(),
            'dependencias' => $this->buscarDependenciasPorLegalizacao($legalizacaoId),
        ];
    }

    /**
     * Criar nova legalização
     */
    public function criar(array $dados, ?User $user = null): Legalizacao
    {
        return DB::transaction(function () use ($dados, $user) {
            $terreno = $this->validarTerreno($dados['terreno_id']);
            $this->validarLegalizacaoUnicaPorTerreno($dados['terreno_id']);
            $actingUser = $user ?? Auth::user();
            $actingUserId = $actingUser?->id ?? Auth::id();

            $legalizacao = $this->repository->create([
                'terreno_id' => $dados['terreno_id'],
                'nome' => $dados['nome'] ?? "Legalização - {$terreno->nome}",
                'responsavel_id' => $dados['responsavel_id'] ?? null,
                'status' => LegalizacaoStatus::PLANEJADO,
                'data_inicio_prevista' => $dados['data_inicio_prevista'] ?? null,
                'data_conclusao_prevista' => $dados['data_conclusao_prevista'] ?? null,
                'custo_total_previsto' => $dados['custo_total_previsto'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'percentual_concluido' => 0,
                'created_by' => $actingUserId,
                'updated_by' => $actingUserId,
            ]);

            $this->workflowService->transition(
                $terreno,
                WorkflowStatus::LEGALIZANDO->value,
                $actingUser,
                'legalization_created',
                $dados['observacoes'] ?? null,
            );

            return $this->repository->loadDetail($legalizacao);
        });
    }

    /**
     * Atualizar legalização
     */
    public function atualizar(Legalizacao $legalizacao, array $dados, ?User $user = null): Legalizacao
    {
        $actingUser = $user ?? Auth::user();
        $dados['updated_by'] = $actingUser?->id ?? Auth::id();

        $legalizacao = $this->repository->update($legalizacao, $dados);

        if (isset($dados['status'])) {
            $workflowStatus = WorkflowStatus::LEGALIZANDO->value;

            $this->workflowService->transition(
                $legalizacao->terreno()->firstOrFail(),
                $workflowStatus,
                $actingUser,
                'legalization_updated',
                $dados['observacoes'] ?? null,
            );
        }

        return $this->repository->loadDetail($legalizacao);
    }

    /**
     * Excluir legalização (soft delete)
     */
    public function excluir(Legalizacao $legalizacao): bool
    {
        return $this->repository->delete($legalizacao);
    }

    /**
     * Sincronizar etapas e dependências em lote (endpoint gantt/sync)
     */
    public function syncGantt(Legalizacao $legalizacao, array $dados): array
    {
        return DB::transaction(function () use ($legalizacao, $dados) {
            if (! empty($dados['etapas'])) {
                foreach ($dados['etapas'] as $etapaData) {
                    $this->syncOrCreateEtapa($legalizacao, $etapaData);
                }
            }

            if (! empty($dados['dependencias'])) {
                foreach ($dados['dependencias'] as $depData) {
                    $depData['legalizacao_id'] = $legalizacao->id;
                    $this->validarDependencia($depData);
                    $this->syncOrCreateDependencia($legalizacao, $depData);
                }
            }

            if (! empty($dados['deleted_etapa_ids'])) {
                $this->deletarEtapas($dados['deleted_etapa_ids'], $legalizacao->id);
            }

            if (! empty($dados['deleted_dependencia_ids'])) {
                $this->deletarDependencias($dados['deleted_dependencia_ids'], $legalizacao->id);
            }

            $legalizacao->recalcularProgresso();

            return $this->buscar($legalizacao->id);
        });
    }

    /**
     * Sincronizar ou criar etapa
     */
    protected function syncOrCreateEtapa(Legalizacao $legalizacao, array $dados): LegalizacaoEtapa
    {
        $normalized = $this->normalizarEtapaPayload($dados);
        $hasParentId = array_key_exists('parent_id', $dados);

        if (! empty($dados['id'])) {
            $etapa = $this->etapaRepository->findByIdAndLegalizacao($dados['id'], $legalizacao->id);

            $updatePayload = [
                'titulo' => $normalized['titulo'] ?? $etapa->getAttribute('titulo'),
                'descricao' => $normalized['descricao'] ?? $etapa->getAttribute('descricao'),
                'ordem' => $normalized['ordem'] ?? $etapa->getAttribute('ordem'),
                'parent_id' => $hasParentId ? ($normalized['parent_id'] ?? null) : $etapa->getAttribute('parent_id'),
                'inicio_planejado' => $normalized['inicio_planejado'] ?? $etapa->getAttribute('inicio_planejado'),
                'fim_planejado' => $normalized['fim_planejado'] ?? $etapa->getAttribute('fim_planejado'),
                'inicio_real' => $normalized['inicio_real'] ?? $etapa->getAttribute('inicio_real'),
                'fim_real' => $normalized['fim_real'] ?? $etapa->getAttribute('fim_real'),
                'status' => $normalized['status'] ?? $etapa->getAttribute('status'),
                'percentual' => $normalized['percentual'] ?? $etapa->getAttribute('percentual'),
                'responsavel_id' => $normalized['responsavel_id'] ?? $etapa->getAttribute('responsavel_id'),
                'cor' => $normalized['cor'] ?? $etapa->getAttribute('cor'),
                'updated_by' => Auth::id(),
            ];

            foreach (['custos', 'tipo_custo', 'valor_custo', 'custo_pago'] as $campoCusto) {
                if (array_key_exists($campoCusto, $normalized)) {
                    $updatePayload[$campoCusto] = $normalized[$campoCusto];
                }
            }

            return $this->etapaRepository->update($etapa, $updatePayload);
        }

        return $this->etapaRepository->create([
            'legalizacao_id' => $legalizacao->id,
            'titulo' => $normalized['titulo'] ?? 'Etapa sem título',
            'descricao' => $normalized['descricao'] ?? null,
            'ordem' => $normalized['ordem'] ?? $this->proximaOrdem($legalizacao->id),
            'parent_id' => $normalized['parent_id'] ?? null,
            'inicio_planejado' => $normalized['inicio_planejado'] ?? now()->toDateString(),
            'fim_planejado' => $normalized['fim_planejado'] ?? ($normalized['inicio_planejado'] ?? now()->toDateString()),
            'inicio_real' => $normalized['inicio_real'] ?? null,
            'fim_real' => $normalized['fim_real'] ?? null,
            'status' => LegalizacaoEtapaStatus::PENDENTE,
            'percentual' => $normalized['percentual'] ?? 0,
            'responsavel_id' => $normalized['responsavel_id'] ?? null,
            'cor' => $normalized['cor'] ?? null,
            'tipo_custo' => $normalized['tipo_custo'] ?? null,
            'valor_custo' => $normalized['valor_custo'] ?? null,
            'custo_pago' => $normalized['custo_pago'] ?? false,
            'custos' => $normalized['custos'] ?? [],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    /**
     * Normaliza os dados recebidos para o formato da etapa.
     */
    protected function normalizarEtapaPayload(array $dados): array
    {
        $titulo = $dados['titulo'] ?? $dados['nome'] ?? null;
        $inicioPlanejado = $dados['inicio_planejado'] ?? $dados['data_prevista'] ?? null;
        $fimPlanejado = $dados['fim_planejado'] ?? $dados['data_conclusao'] ?? $inicioPlanejado;

        $status = $dados['status'] ?? null;
        if ($status === 'cancelada') {
            $status = LegalizacaoEtapaStatus::BLOQUEADA;
        }
        if ($status === 'nao_iniciada') {
            $status = LegalizacaoEtapaStatus::PENDENTE;
        }

        $percentual = $dados['percentual'] ?? null;
        if ($percentual === null && $status === LegalizacaoEtapaStatus::CONCLUIDA) {
            $percentual = 100;
        }

        $temPayloadCustos = $this->etapaTemAlgumCampoDeCustoRaiz($dados) || array_key_exists('custos', $dados);
        $custos = $this->normalizarCustosPayload((array) ($dados['custos'] ?? []));

        if (empty($custos) && $this->etapaTemAlgumCampoDeCustoRaiz($dados)) {
            $valorCustoRaiz = $dados['valor_custo'] ?? null;
            $custoPagoRaiz = $this->normalizarBoolean($dados['custo_pago'] ?? false);

            if ($valorCustoRaiz !== null || isset($dados['tipo_custo'])) {
                $custos[] = [
                    'tipo_custo' => $dados['tipo_custo'] ?? null,
                    'valor_custo' => $valorCustoRaiz,
                    'custo_pago' => $custoPagoRaiz,
                ];
            }
        }

        $resumoCustos = $this->resumirCustos($custos, $dados);

        $payload = [
            'titulo' => $titulo,
            'descricao' => $dados['descricao'] ?? null,
            'ordem' => $dados['ordem'] ?? null,
            'parent_id' => $dados['parent_id'] ?? null,
            'inicio_planejado' => $inicioPlanejado,
            'fim_planejado' => $fimPlanejado,
            'inicio_real' => $dados['inicio_real'] ?? null,
            'fim_real' => $dados['fim_real'] ?? null,
            'status' => $status,
            'percentual' => $percentual,
            'responsavel_id' => $dados['responsavel_id'] ?? null,
            'cor' => $dados['cor'] ?? null,
            'tipo_custo' => $resumoCustos['tipo_custo'],
            'valor_custo' => $resumoCustos['valor_custo'],
            'custo_pago' => $resumoCustos['custo_pago'],
        ];

        if ($temPayloadCustos) {
            $payload['custos'] = $custos;
        }

        return $payload;
    }

    /**
     * Verifica se o payload possui campos de custo no nível raiz.
     */
    protected function etapaTemAlgumCampoDeCustoRaiz(array $dados): bool
    {
        return array_key_exists('tipo_custo', $dados)
            || array_key_exists('valor_custo', $dados)
            || array_key_exists('custo_pago', $dados);
    }

    /**
     * Normaliza a lista de custos associados a uma etapa.
     *
     * @param  array<int, mixed>  $custos
     * @return array<int, array<string, mixed>>
     */
    protected function normalizarCustosPayload(array $custos): array
    {
        $normalizados = [];

        foreach ($custos as $custo) {
            if (! is_array($custo)) {
                continue;
            }

            $tipo = $custo['tipo_custo'] ?? null;
            $valor = $custo['valor_custo'] ?? null;
            $pago = $custo['custo_pago'] ?? false;

            if ($tipo === null && $valor === null) {
                continue;
            }

            $normalizados[] = [
                'tipo_custo' => $tipo,
                'valor_custo' => $valor,
                'custo_pago' => $this->normalizarBoolean($pago),
            ];
        }

        return $normalizados;
    }

    /**
     * Converte diversos formatos de entrada para booleano.
     */
    protected function normalizarBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = mb_strtolower(trim($value));

            return in_array($value, ['1', 'true', 'yes', 'sim'], true);
        }

        return false;
    }

    /**
     * Resume os custos da etapa para os campos legados (tipo, valor, pago).
     *
     * @param  array<int, array<string, mixed>>  $custos
     * @param  array<string, mixed>  $dadosOriginais
     * @return array{tipo_custo: mixed, valor_custo: mixed, custo_pago: mixed}
     */
    protected function resumirCustos(array $custos, array $dadosOriginais): array
    {
        if (empty($custos)) {
            $valorCusto = $dadosOriginais['valor_custo'] ?? null;
            $custoPago = $dadosOriginais['custo_pago'] ?? null;
            $custoPago = $custoPago !== null ? $this->normalizarBoolean($custoPago) : false;

            return [
                'tipo_custo' => $dadosOriginais['tipo_custo'] ?? null,
                'valor_custo' => $valorCusto,
                'custo_pago' => $custoPago,
            ];
        }

        $total = array_sum(array_map(
            fn ($custo) => (float) ($custo['valor_custo'] ?? 0),
            $custos
        ));
        $tipo = count($custos) === 1
            ? ($custos[0]['tipo_custo'] ?? null)
            : ($dadosOriginais['tipo_custo'] ?? 'Diversos');
        $pago = collect($custos)->every(fn ($custo) => (bool) ($custo['custo_pago'] ?? false));

        return [
            'tipo_custo' => $tipo,
            'valor_custo' => $total,
            'custo_pago' => $pago,
        ];
    }

    /**
     * Sincronizar ou criar dependência
     */
    protected function syncOrCreateDependencia(Legalizacao $legalizacao, array $dados): LegalizacaoDependencia
    {
        $exists = $this->etapaRepository->dependencyExists(
            $legalizacao->id,
            $dados['etapa_origem_id'],
            $dados['etapa_destino_id']
        );

        if ($exists) {
            return $this->etapaRepository->findDependencyByEndpoints(
                $legalizacao->id,
                (int) $dados['etapa_origem_id'],
                (int) $dados['etapa_destino_id']
            );
        }

        return $this->etapaRepository->createDependency([
            'legalizacao_id' => $legalizacao->id,
            'etapa_origem_id' => $dados['etapa_origem_id'],
            'etapa_destino_id' => $dados['etapa_destino_id'],
            'tipo' => $dados['tipo'] ?? 'FS',
        ]);
    }

    /**
     * Deletar etapas em lote
     */
    protected function deletarEtapas(array $etapaIds, int $legalizacaoId): void
    {
        $this->etapaRepository->deleteMany($etapaIds, $legalizacaoId);
    }

    /**
     * Deletar dependências em lote
     */
    protected function deletarDependencias(array $dependenciaIds, int $legalizacaoId): void
    {
        $this->etapaRepository->deleteDependencies($dependenciaIds, $legalizacaoId);
    }

    /**
     * Calcular próxima ordem para etapa
     */
    public function proximaOrdem(int $legalizacaoId): int
    {
        return $this->etapaRepository->getNextOrdem($legalizacaoId);
    }

    /**
     * Adicionar etapa a uma legalização
     */
    public function adicionarEtapa(Legalizacao $legalizacao, array $dados): LegalizacaoEtapa
    {
        $etapa = $this->etapaRepository->create($dados);
        $legalizacao->recalcularProgresso();

        return $etapa;
    }

    /**
     * Atualizar etapa
     */
    public function atualizarEtapa(LegalizacaoEtapa $etapa, array $dados): LegalizacaoEtapa
    {
        $etapa = $this->etapaRepository->update($etapa, $dados);
        $etapa->legalizacao?->recalcularProgresso();

        return $etapa;
    }

    /**
     * Remover etapa
     */
    public function removerEtapa(LegalizacaoEtapa $etapa): void
    {
        $legalizacao = $etapa->legalizacao;
        $this->etapaRepository->delete($etapa);
        $legalizacao?->recalcularProgresso();
    }

    /**
     * Validar dependência (anti-ciclo)
     */
    public function validarDependencia(array $dados): void
    {
        if ($dados['etapa_origem_id'] === $dados['etapa_destino_id']) {
            throw ValidationException::withMessages([
                'dependencias' => ['Uma etapa não pode depender de si mesma.'],
            ]);
        }

        if ($this->criariaCiclo((int) $dados['etapa_origem_id'], (int) $dados['etapa_destino_id'])) {
            throw ValidationException::withMessages([
                'dependencias' => ['Esta dependência criaria um ciclo circular.'],
            ]);
        }
    }

    /**
     * Verificar se cria ciclo: destino nao pode alcancar origem.
     */
    protected function criariaCiclo(int $origemId, int $destinoId): bool
    {
        return $this->etapaRepository->wouldCreateCycle($origemId, $destinoId);
    }

    /**
     * Buscar dependências de uma legalização
     */
    protected function buscarDependenciasPorLegalizacao(int $legalizacaoId): Collection
    {
        return $this->repository->listDependenciasByLegalizacao($legalizacaoId);
    }

    /**
     * Listar terrenos elegíveis (status "Contrato Assinado" e sem legalização ativa)
     */
    public function listarTerrenosElegiveis(array $filtros = []): LengthAwarePaginator
    {
        return $this->repository->paginateEligibleTerrenos($filtros);
    }

    public function recalcularProgresso(Legalizacao $legalizacao): Legalizacao
    {
        return $this->repository->recalculateProgress($legalizacao);
    }

    /**
     * Validar terreno e elegibilidade para legalização
     */
    protected function validarTerreno(int $terrenoId): Terreno
    {
        $terreno = $this->repository->findTerrenoOrFail($terrenoId);

        if ($terreno->workflow_status_code !== WorkflowStatus::CONTRATO_ASSINADO->value) {
            throw ValidationException::withMessages([
                'terreno_id' => ['Somente terrenos com status "Contrato Assinado" podem iniciar legalização.'],
            ]);
        }

        return $terreno;
    }

    /**
     * Validar unicidade de legalização por terreno (somente ativas)
     */
    protected function validarLegalizacaoUnicaPorTerreno(int $terrenoId): void
    {
        $exists = $this->repository->existsByTerreno($terrenoId);

        if ($exists) {
            throw ValidationException::withMessages([
                'terreno_id' => ['Já existe uma legalização para este terreno.'],
            ]);
        }
    }

    /**
     * Reordenar etapas
     */
    public function reordenarEtapas(Legalizacao $legalizacao, array $ordens): void
    {
        $updatedBy = Auth::id();

        DB::transaction(function () use ($legalizacao, $ordens, $updatedBy) {
            foreach ($ordens as $ordemData) {
                if (! isset($ordemData['id'], $ordemData['ordem'])) {
                    continue;
                }

                $etapa = $this->etapaRepository->findByIdAndLegalizacao(
                    $ordemData['id'],
                    $legalizacao->id
                );

                $this->etapaRepository->update($etapa, [
                    'ordem' => $ordemData['ordem'],
                    'updated_by' => $updatedBy,
                ]);
            }
        });
    }

    /**
     * Atualizar status de etapa e recalcular progresso
     */
    public function atualizarStatusEtapa(LegalizacaoEtapa $etapa, string $status): LegalizacaoEtapa
    {
        $payload = [
            'status' => $status,
            'updated_by' => Auth::id(),
        ];

        if ($status === LegalizacaoEtapaStatus::CONCLUIDA->value) {
            $payload['percentual'] = 100;
            if ($etapa->getAttribute('fim_real') === null) {
                $payload['fim_real'] = now()->toDateString();
            }
        }

        if ($status === LegalizacaoEtapaStatus::EM_ANDAMENTO->value && $etapa->getAttribute('inicio_real') === null) {
            $payload['inicio_real'] = now()->toDateString();
        }

        $etapa = $this->etapaRepository->update($etapa, $payload);

        $legalizacao = $etapa->legalizacao;
        $legalizacao?->recalcularProgresso();

        if ($legalizacao && $status === LegalizacaoEtapaStatus::CONCLUIDA->value) {
            $temPendenciaCritica = $this->repository->hasCriticalOpenPendencia($legalizacao->id);

            if ($temPendenciaCritica) {
                $this->workflowService->transition(
                    $legalizacao->terreno()->firstOrFail(),
                    WorkflowStatus::LEGALIZANDO->value,
                    Auth::user(),
                    'legalization_issue',
                    'Existem pendências críticas abertas.',
                );
            }
        }

        return $etapa->fresh(['legalizacao']);
    }
}
