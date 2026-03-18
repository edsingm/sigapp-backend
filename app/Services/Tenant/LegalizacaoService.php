<?php

namespace App\Services\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoDependencia;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\LegalizacaoPendencia;
use App\Models\Tenant\Terreno;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LegalizacaoService
{
    public function __construct(
        protected LandWorkflowService $workflowService
    ) {}

    /**
     * Listar legalizações com filtros e paginação
     */
    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $query = Legalizacao::with([
            'terreno',
            'responsavel',
            'etapas',
            'createdBy',
            'updatedBy',
        ]);

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhereHas('terreno', function ($terrenoQuery) use ($search) {
                        $terrenoQuery->where('nome', 'like', "%{$search}%")
                            ->orWhere('endereco', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filtros['terreno_id'])) {
            $query->where('terreno_id', $filtros['terreno_id']);
        }

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filtros['per_page'] ?? 10);
    }

    /**
     * Buscar legalização por ID com relacionamentos
     */
    public function buscar(int $legalizacaoId): array
    {
        $legalizacao = Legalizacao::with([
            'terreno',
            'responsavel',
            'etapas.responsavel',
            'etapas.dependenciasDestino',
            'etapas.dependenciasOrigem',
            'pendencias',
            'createdBy',
            'updatedBy',
        ])->findOrFail($legalizacaoId);

        return [
            'legalizacao' => $legalizacao,
            'etapas' => $legalizacao->etapas->sortBy('ordem')->values(),
            'dependencias' => $this->buscarDependenciasPorLegalizacao($legalizacaoId),
        ];
    }

    /**
     * Criar nova legalização
     */
    public function criar(array $dados): Legalizacao
    {
        return DB::transaction(function () use ($dados) {
            $terreno = $this->validarTerreno($dados['terreno_id']);
            $this->validarLegalizacaoUnicaPorTerreno($dados['terreno_id']);

            $legalizacao = Legalizacao::create([
                'terreno_id' => $dados['terreno_id'],
                'nome' => $dados['nome'] ?? "Legalização - {$terreno->nome}",
                'responsavel_id' => $dados['responsavel_id'] ?? null,
                'status' => Legalizacao::STATUS_PLANEJADO,
                'data_inicio_prevista' => $dados['data_inicio_prevista'] ?? null,
                'data_conclusao_prevista' => $dados['data_conclusao_prevista'] ?? null,
                'custo_total_previsto' => $dados['custo_total_previsto'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'percentual_concluido' => 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->workflowService->transition(
                $terreno,
                WorkflowStatus::LEGALIZANDO->value,
                Auth::user(),
                'legalization_created',
                $dados['observacoes'] ?? null,
            );

            return $legalizacao->load(['terreno', 'responsavel', 'createdBy', 'updatedBy']);
        });
    }

    /**
     * Atualizar legalização
     */
    public function atualizar(Legalizacao $legalizacao, array $dados): Legalizacao
    {
        $dados['updated_by'] = Auth::id();

        $legalizacao->update($dados);

        if (isset($dados['status'])) {
            $workflowStatus = WorkflowStatus::LEGALIZANDO->value;

            $this->workflowService->transition(
                $legalizacao->terreno()->firstOrFail(),
                $workflowStatus,
                Auth::user(),
                'legalization_updated',
                $dados['observacoes'] ?? null,
            );
        }

        return $legalizacao->fresh(['terreno', 'responsavel', 'etapas', 'createdBy', 'updatedBy']);
    }

    /**
     * Excluir legalização (soft delete)
     */
    public function excluir(int $legalizacaoId): bool
    {
        $legalizacao = Legalizacao::findOrFail($legalizacaoId);

        return $legalizacao->delete();
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
            $etapa = LegalizacaoEtapa::where('legalizacao_id', $legalizacao->id)
                ->findOrFail($dados['id']);

            $updatePayload = [
                'titulo' => $normalized['titulo'] ?? $etapa->titulo,
                'descricao' => $normalized['descricao'] ?? $etapa->descricao,
                'ordem' => $normalized['ordem'] ?? $etapa->ordem,
                'parent_id' => $hasParentId ? ($normalized['parent_id'] ?? null) : $etapa->parent_id,
                'inicio_planejado' => $normalized['inicio_planejado'] ?? $etapa->inicio_planejado,
                'fim_planejado' => $normalized['fim_planejado'] ?? $etapa->fim_planejado,
                'inicio_real' => $normalized['inicio_real'] ?? $etapa->inicio_real,
                'fim_real' => $normalized['fim_real'] ?? $etapa->fim_real,
                'status' => $normalized['status'] ?? $etapa->status,
                'percentual' => $normalized['percentual'] ?? $etapa->percentual,
                'responsavel_id' => $normalized['responsavel_id'] ?? $etapa->responsavel_id,
                'cor' => $normalized['cor'] ?? $etapa->cor,
                'updated_by' => Auth::id(),
            ];

            foreach (['custos', 'tipo_custo', 'valor_custo', 'custo_pago'] as $campoCusto) {
                if (array_key_exists($campoCusto, $normalized)) {
                    $updatePayload[$campoCusto] = $normalized[$campoCusto];
                }
            }

            $etapa->update($updatePayload);

            return $etapa;
        }

        return LegalizacaoEtapa::create([
            'legalizacao_id' => $legalizacao->id,
            'titulo' => $normalized['titulo'] ?? 'Etapa sem título',
            'descricao' => $normalized['descricao'] ?? null,
            'ordem' => $normalized['ordem'] ?? $this->proximaOrdem($legalizacao->id),
            'parent_id' => $normalized['parent_id'] ?? null,
            'inicio_planejado' => $normalized['inicio_planejado'] ?? now()->toDateString(),
            'fim_planejado' => $normalized['fim_planejado'] ?? ($normalized['inicio_planejado'] ?? now()->toDateString()),
            'inicio_real' => $normalized['inicio_real'] ?? null,
            'fim_real' => $normalized['fim_real'] ?? null,
            'status' => $normalized['status'] ?? LegalizacaoEtapa::STATUS_PENDENTE,
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
            $status = LegalizacaoEtapa::STATUS_BLOQUEADA;
        }
        if ($status === 'nao_iniciada') {
            $status = LegalizacaoEtapa::STATUS_PENDENTE;
        }

        $percentual = $dados['percentual'] ?? null;
        if ($percentual === null && $status === LegalizacaoEtapa::STATUS_CONCLUIDA) {
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
        $existing = LegalizacaoDependencia::where('legalizacao_id', $legalizacao->id)
            ->where('etapa_origem_id', $dados['etapa_origem_id'])
            ->where('etapa_destino_id', $dados['etapa_destino_id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return LegalizacaoDependencia::create([
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
        LegalizacaoEtapa::where('legalizacao_id', $legalizacaoId)
            ->whereIn('id', $etapaIds)
            ->delete();
    }

    /**
     * Deletar dependências em lote
     */
    protected function deletarDependencias(array $dependenciaIds, int $legalizacaoId): void
    {
        LegalizacaoDependencia::where('legalizacao_id', $legalizacaoId)
            ->whereIn('id', $dependenciaIds)
            ->delete();
    }

    /**
     * Calcular próxima ordem para etapa
     */
    public function proximaOrdem(int $legalizacaoId): int
    {
        $ultimaOrdem = LegalizacaoEtapa::where('legalizacao_id', $legalizacaoId)
            ->max('ordem');

        return $ultimaOrdem ? $ultimaOrdem + 1 : 1;
    }

    /**
     * Validar dependência (anti-ciclo)
     */
    public function validarDependencia(array $dados): void
    {
        if ($dados['etapa_origem_id'] === $dados['etapa_destino_id']) {
            throw new Exception('Uma etapa não pode depender de si mesma');
        }

        if ($this->criariaCiclo((int) $dados['etapa_origem_id'], (int) $dados['etapa_destino_id'])) {
            throw new Exception('Esta dependência criaria um ciclo circular');
        }
    }

    /**
     * Verificar se cria ciclo: destino nao pode alcancar origem.
     */
    protected function criariaCiclo(int $origemId, int $destinoId): bool
    {
        $visitados = [];
        $stack = [$destinoId];

        while (! empty($stack)) {
            $atual = array_pop($stack);

            if ($atual === $origemId) {
                return true;
            }

            if (in_array($atual, $visitados, true)) {
                continue;
            }

            $visitados[] = $atual;

            $proximos = LegalizacaoDependencia::where('etapa_origem_id', $atual)
                ->pluck('etapa_destino_id')
                ->all();

            foreach ($proximos as $proximo) {
                if (! in_array($proximo, $visitados, true)) {
                    $stack[] = (int) $proximo;
                }
            }
        }

        return false;
    }

    /**
     * Buscar dependências de uma legalização
     */
    protected function buscarDependenciasPorLegalizacao(int $legalizacaoId): Collection
    {
        return LegalizacaoDependencia::with(['etapaOrigem', 'etapaDestino'])
            ->where('legalizacao_id', $legalizacaoId)
            ->get();
    }

    /**
     * Listar terrenos elegíveis (status "Contrato Assinado" e sem legalização ativa)
     */
    public function listarTerrenosElegiveis(array $filtros = []): LengthAwarePaginator
    {
        $query = Terreno::query()
            ->with(['cidade', 'responsavel'])
            ->where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)
            ->whereDoesntHave('legalizacao');

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('endereco', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')
            ->paginate($filtros['per_page'] ?? 10);
    }

    /**
     * Validar terreno e elegibilidade para legalização
     */
    protected function validarTerreno(int $terrenoId): Terreno
    {
        $terreno = Terreno::find($terrenoId);

        if (! $terreno) {
            throw new Exception('Terreno não encontrado');
        }

        if ($terreno->workflow_status_code !== WorkflowStatus::CONTRATO_ASSINADO->value) {
            throw new Exception('Somente terrenos com status "Contrato Assinado" podem iniciar legalização');
        }

        return $terreno;
    }

    /**
     * Validar unicidade de legalização por terreno (somente ativas)
     */
    protected function validarLegalizacaoUnicaPorTerreno(int $terrenoId): void
    {
        $exists = Legalizacao::where('terreno_id', $terrenoId)
            ->exists();

        if ($exists) {
            throw new Exception('Já existe uma legalização para este terreno');
        }
    }

    /**
     * Reordenar etapas
     */
    public function reordenarEtapas(Legalizacao $legalizacao, array $ordens): void
    {
        DB::transaction(function () use ($legalizacao, $ordens) {
            foreach ($ordens as $ordemData) {
                if (! isset($ordemData['id'], $ordemData['ordem'])) {
                    continue;
                }

                $etapa = LegalizacaoEtapa::where('legalizacao_id', $legalizacao->id)
                    ->findOrFail($ordemData['id']);

                $etapa->update([
                    'ordem' => $ordemData['ordem'],
                    'updated_by' => Auth::id(),
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

        if ($status === LegalizacaoEtapa::STATUS_CONCLUIDA) {
            $payload['percentual'] = 100;
            if (! $etapa->fim_real) {
                $payload['fim_real'] = now()->toDateString();
            }
        }

        if ($status === LegalizacaoEtapa::STATUS_EM_ANDAMENTO && ! $etapa->inicio_real) {
            $payload['inicio_real'] = now()->toDateString();
        }

        $etapa->update($payload);

        $legalizacao = $etapa->legalizacao;
        $legalizacao?->recalcularProgresso();

        if ($legalizacao && $status === LegalizacaoEtapa::STATUS_CONCLUIDA) {
            $temPendenciaCritica = LegalizacaoPendencia::query()
                ->where('legalizacao_id', $legalizacao->id)
                ->where('status', 'open')
                ->where('is_critical', true)
                ->exists();

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
